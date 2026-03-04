<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateVolunteerStatusRequest;
use App\Http\Resources\HelpRequestResource;
use App\Models\HelpRequest;
use App\Models\Review;
use App\Models\VolunteerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerController extends Controller
{
    use ApiResponse;

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
            ->with(['requester', 'volunteer'])
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
            ->where('status', 'active')
            ->with(['requester', 'volunteer'])
            ->latest('accepted_at')
            ->latest('id');

        $requests = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();

        return $this->successResponse([
            'counts' => $this->dashboardCounts($user->id),
            'status_banner' => 'Assistance in Progress',
            'requests' => $this->paginatedData($requests, HelpRequestResource::collection($requests->getCollection())),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can view request history.', [], 403);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HelpRequest::query()
            ->where('volunteer_id', $user->id)
            ->where('status', 'completed')
            ->with(['requester', 'volunteer'])
            ->latest('completed_at')
            ->latest('id');

        $requests = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();

        return $this->successResponse([
            'counts' => $this->dashboardCounts($user->id),
            'impact' => $this->impactSummary($user->id),
            'requests' => $this->paginatedData($requests, HelpRequestResource::collection($requests->getCollection())),
        ]);
    }

    public function impact(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can view impact summary.', [], 403);
        }

        return $this->successResponse([
            'counts' => $this->dashboardCounts($user->id),
            'impact' => $this->impactSummary($user->id),
        ]);
    }

    private function dashboardCounts(int $volunteerId): array
    {
        return [
            'incoming' => HelpRequest::query()->where('status', 'pending')->count(),
            'active' => HelpRequest::query()
                ->where('status', 'active')
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

        // We currently don't store explicit volunteer-assist ratings, so this uses user review averages as a fallback metric.
        $avgRating = (float) (Review::query()
            ->where('user_id', $volunteerId)
            ->avg('rating') ?? 0);

        return [
            'total_assists' => $totalAssists,
            'avg_rating' => round($avgRating, 2),
            'this_week' => $thisWeek,
        ];
    }
}
