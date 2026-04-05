<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateVolunteerStatusRequest;
use App\Http\Requests\Api\VolunteerHistoryIndexRequest;
use App\Http\Resources\HelpRequestResource;
use App\Http\Resources\VolunteerHistoryItemResource;
use App\Models\HelpRequest;
use App\Models\VolunteerReview;
use App\Models\VolunteerSession;
use App\Services\VolunteerAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VolunteerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VolunteerAnalyticsService $analyticsService,
    ) {}

    public function status(UpdateVolunteerStatusRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can use live mode.', [], 403);
        }

        $validated = $request->validated();

        $session = VolunteerSession::query()->firstOrNew([
            'user_id' => $user->id,
            'ended_at' => null,
        ]);

        $session->user_id = $user->id;
        $session->is_live = (bool) $validated['is_live'];
        $session->started_at = $session->started_at ?? now();
        $session->ended_at = $session->is_live ? null : now();
        $session->last_lat = $validated['lat'] ?? $session->last_lat;
        $session->last_lng = $validated['lng'] ?? $session->last_lng;
        $session->last_seen_at = now();
        $session->save();

        return $this->successResponse([
            'is_live' => $session->is_live,
            'last_seen_at' => optional($session->last_seen_at)->toIso8601String(),
        ]);
    }

    public function incoming(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can view incoming requests.', [], 403);
        }

        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HelpRequest::query()
            ->where('status', 'pending')
            ->with(['requester', 'volunteer', 'payment'])
            ->orderByRaw("CASE urgency_level WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->latest();

        if (array_key_exists('lat', $validated) && array_key_exists('lng', $validated)
            && $validated['lat'] !== null && $validated['lng'] !== null) {
            $lat = (float) $validated['lat'];
            $lng = (float) $validated['lng'];

            $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(from_lat)) * cos(radians(from_lng) - radians(?)) + sin(radians(?)) * sin(radians(from_lat))))';
            $query->select('help_requests.*')->selectRaw("{$distanceSql} as distance_km", [$lat, $lng, $lat])->orderBy('distance_km');
        }

        $requests = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();
        $counts = $this->dashboardCounts($user->id);

        return $this->successResponse([
            'counts' => $counts,
            'incoming_alert' => [
                'count' => $counts['incoming'],
                'message' => sprintf('%d people need your help nearby', $counts['incoming']),
            ],
            'requests' => $this->paginatedData($requests, HelpRequestResource::collection($requests->getCollection())),
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can view active requests.', [], 403);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HelpRequest::query()
            ->where('volunteer_id', $user->id)
            ->whereIn('status', ['active', 'confirmed', 'pending_payment'])
            ->with(['requester', 'volunteer', 'payment'])
            ->latest('accepted_at')
            ->latest('id');

        $requests = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();

        return $this->successResponse([
            'counts' => $this->dashboardCounts($user->id),
            'status_banner' => 'Assistance in Progress',
            'requests' => $this->paginatedData($requests, HelpRequestResource::collection($requests->getCollection())),
        ]);
    }

    /**
     * History tab: expanded with summary, filters, pagination, and settlement data.
     * Backward-compatible: keeps 'counts' and 'impact' keys alongside new 'summary', 'data', 'meta', 'filters'.
     */
    public function history(VolunteerHistoryIndexRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can view request history.', [], 403);
        }

        $validated = $request->validated();
        $volunteerId = $user->id;

        // ── Summary cards ──
        $requestsThisWeek = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->startOfWeek(Carbon::SATURDAY))
            ->count();

        $thisMonthNet = $this->analyticsService->netEarningsCents(
            $volunteerId,
            Carbon::now()->startOfMonth()->toDateTimeString(),
            Carbon::now()->endOfMonth()->toDateTimeString(),
        );

        $thisWeekNet = $this->analyticsService->netEarningsCents(
            $volunteerId,
            Carbon::now()->startOfWeek(Carbon::SATURDAY)->toDateTimeString(),
            Carbon::now()->endOfWeek(Carbon::FRIDAY)->toDateTimeString(),
        );

        // ── Query with filters ──
        $status = $validated['status'] ?? 'completed';
        $query = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->with(['requester']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (!empty($validated['from'])) {
            $query->whereDate('completed_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->whereDate('completed_at', '<=', $validated['to']);
        }
        if (!empty($validated['assistance_type'])) {
            $query->where('assistance_type', $validated['assistance_type']);
        }

        $sortBy = $validated['sort_by'] ?? 'completed_at';
        $sortDir = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir)->orderByDesc('id');

        $perPage = (int) ($validated['per_page'] ?? 15);
        $paginated = $query->paginate($perPage)->withQueryString();

        // ── Backward compat ──
        $counts = $this->dashboardCounts($volunteerId);
        $legacyImpact = $this->impactSummary($volunteerId);

        return $this->successResponse([
            // backward-compatible keys
            'counts' => $counts,
            'impact' => $legacyImpact,
            // new History tab contract
            'summary' => [
                'requests_this_week' => $requestsThisWeek,
                'this_week_net' => round($thisWeekNet / 100, 2),
                'current_month_net' => round($thisMonthNet / 100, 2),
                'this_month_net_earnings' => round($thisMonthNet / 100, 2),
                'currency' => 'EGP',
            ],
            'data' => VolunteerHistoryItemResource::collection($paginated->getCollection()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'filters' => [
                'status' => $status,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
        ]);
    }

    /**
     * Impact / Overview tab: expanded with analytics data.
     * Backward-compatible: keeps 'counts' and 'impact' keys alongside new overview data.
     */
    public function impact(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can view impact summary.', [], 403);
        }

        $volunteerId = $user->id;
        $overview = $this->analyticsService->overview($volunteerId);

        // Merge backward-compatible keys with new overview data
        return $this->successResponse(array_merge(
            [
                'counts' => $this->dashboardCounts($volunteerId),
                'impact' => $this->impactSummary($volunteerId),
            ],
            $overview,
        ));
    }

    private function dashboardCounts(int $volunteerId): array
    {
        return [
            'incoming' => HelpRequest::query()->where('status', 'pending')->count(),
            'active' => HelpRequest::query()
                ->whereIn('status', ['active', 'confirmed', 'pending_payment'])
                ->where('volunteer_id', $volunteerId)
                ->count(),
            'history' => HelpRequest::query()
                ->where('status', 'completed')
                ->where('volunteer_id', $volunteerId)
                ->count(),
        ];
    }

    private function impactSummary(int $volunteerId): array
    {
        $totalAssists = HelpRequest::query()
            ->where('status', 'completed')
            ->where('volunteer_id', $volunteerId)
            ->count();

        $thisWeek = HelpRequest::query()
            ->where('status', 'completed')
            ->where('volunteer_id', $volunteerId)
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $avgRating = (float) (VolunteerReview::query()
            ->where('volunteer_id', $volunteerId)
            ->avg('rating') ?? 0);

        return [
            'total_assists' => $totalAssists,
            'avg_rating' => round($avgRating, 2),
            'this_week' => $thisWeek,
        ];
    }
}
