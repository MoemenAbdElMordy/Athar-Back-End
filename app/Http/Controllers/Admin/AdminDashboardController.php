<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Flag;
use App\Models\HelpRequest;
use App\Models\Location;
use App\Models\Notification;
use App\Models\PlaceSubmission;
use App\Models\Tutorial;
use App\Models\User;
use App\Models\VolunteerReview;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $adminId = Auth::guard('web')->id();

        $hasVolunteerReviews = Schema::hasTable('volunteer_reviews');
        $hasFeeColumns = Schema::hasColumn('help_requests', 'fee_amount_cents');
        $hasPaymentColumns = Schema::hasColumn('help_requests', 'payment_method');

        // ── Core counts (always safe) ──
        $totalUsers = User::query()->where('role', 'user')->count();
        $totalVolunteers = User::query()->where('role', 'volunteer')->whereNotNull('role_verified_at')->count();
        $pendingVolunteers = User::query()->where('role', 'volunteer')->whereNull('role_verified_at')->count();

        $totalHelpRequests = HelpRequest::query()->count();
        $pendingHelpRequests = HelpRequest::query()->where('status', 'pending')->count();
        $activeHelpRequests = HelpRequest::query()->whereIn('status', ['active', 'confirmed', 'pending_payment'])->count();
        $completedHelpRequests = HelpRequest::query()->where('status', 'completed')->count();
        $cancelledHelpRequests = HelpRequest::query()->where('status', 'cancelled')->count();

        $totalLocations = Location::query()->count();
        $pendingSubmissions = PlaceSubmission::query()->where('status', 'pending')->count();
        $openFlags = Flag::query()->where('status', 'open')->count();
        $unreadNotifications = Notification::query()->where('user_id', $adminId)->where('is_read', false)->count();
        $hasAccessibilityReports = Schema::hasTable('accessibility_reports');

        // ── Revenue analytics (safe: check columns exist) ──
        $grossRevenueCents = 0;
        $platformFeesCents = 0;
        $netVolunteerPayCents = 0;
        $cashRequests = 0;
        $cardRequests = 0;
        $cashRevenueCents = 0;
        $cardRevenueCents = 0;

        if ($hasPaymentColumns) {
            $grossRevenueCents = (int) HelpRequest::query()->where('status', 'completed')->sum('service_fee');
            $cashRequests = HelpRequest::query()->where('status', 'completed')->where('payment_method', 'cash')->count();
            $cardRequests = HelpRequest::query()->where('status', 'completed')->where('payment_method', 'card')->count();
            $cashRevenueCents = (int) HelpRequest::query()->where('status', 'completed')->where('payment_method', 'cash')->sum('service_fee');
            $cardRevenueCents = (int) HelpRequest::query()->where('status', 'completed')->where('payment_method', 'card')->sum('service_fee');
        }

        if ($hasFeeColumns) {
            $platformFeesCents = (int) HelpRequest::query()->where('status', 'completed')->sum('fee_amount_cents');
            $netVolunteerPayCents = (int) HelpRequest::query()->where('status', 'completed')->sum('net_amount_cents');
        }

        // ── Volunteer reviews (safe: check table exists) ──
        $totalReviews = 0;
        $avgRating = 0;
        $ratingDistribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        if ($hasVolunteerReviews) {
            $totalReviews = VolunteerReview::query()->count();
            $avgRating = round((float) VolunteerReview::query()->avg('rating'), 2);
            for ($r = 5; $r >= 1; $r--) {
                $ratingDistribution[$r] = VolunteerReview::query()->where('rating', $r)->count();
            }
        }

        // ── Help request status breakdown ──
        $statusBreakdown = [
            ['status' => 'pending', 'count' => $pendingHelpRequests],
            ['status' => 'active', 'count' => $activeHelpRequests],
            ['status' => 'completed', 'count' => $completedHelpRequests],
            ['status' => 'cancelled', 'count' => $cancelledHelpRequests],
        ];

        // ── Assistance type breakdown ──
        $assistanceTypes = [];
        try {
            $assistanceTypes = HelpRequest::query()
                ->where('status', 'completed')
                ->select('assistance_type', DB::raw('COUNT(*) as count'))
                ->groupBy('assistance_type')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['type' => $row->assistance_type, 'count' => (int) $row->count])
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('Dashboard: assistance_types query failed', ['error' => $e->getMessage()]);
        }

        // ── Monthly trends (last 6 months) ──
        $monthlyTrends = $this->monthlyTrends($hasFeeColumns);

        // ── Weekly activity (last 7 days) ──
        $weeklyActivity = $this->weeklyActivity();

        // ── Top volunteers ──
        $topVolunteers = $this->topVolunteers($hasVolunteerReviews, $hasFeeColumns);

        $tutorialsSummary = [
            'total' => Tutorial::query()->count(),
            'published' => Tutorial::query()->where('is_published', true)->count(),
            'draft' => Tutorial::query()->where('is_published', false)->count(),
        ];

        $reportsSummary = [
            'open' => $openFlags,
            'need_info' => Flag::query()->where('status', 'need_info')->count(),
            'resolved' => Flag::query()->where('status', 'resolved')->count(),
            'dismissed' => Flag::query()->where('status', 'dismissed')->count(),
        ];

        $submissionsSummary = [
            'pending' => $pendingSubmissions,
            'approved' => PlaceSubmission::query()->where('status', 'approved')->count(),
            'rejected' => PlaceSubmission::query()->where('status', 'rejected')->count(),
        ];

        $accessibilityOverview = [
            'verified_reports' => 0,
            'wheelchair_accessible' => 0,
            'ramp_available' => 0,
            'elevator_available' => 0,
            'accessible_toilet' => 0,
            'average_place_rating' => round((float) Location::query()->avg('average_rating'), 2),
        ];

        if ($hasAccessibilityReports) {
            $accessibilityOverview['verified_reports'] = Location::query()->whereHas('accessibilityReport', function ($q) {
                $q->where('verified', true);
            })->count();
            $accessibilityOverview['wheelchair_accessible'] = Location::query()->whereHas('accessibilityReport', function ($q) {
                $q->where('wheelchair_accessible', true);
            })->count();
            $accessibilityOverview['ramp_available'] = Location::query()->whereHas('accessibilityReport', function ($q) {
                $q->where('ramp_available', true);
            })->count();
            $accessibilityOverview['elevator_available'] = Location::query()->whereHas('accessibilityReport', function ($q) {
                $q->where('elevator_available', true);
            })->count();
            $accessibilityOverview['accessible_toilet'] = Location::query()->whereHas('accessibilityReport', function ($q) {
                $q->where('accessible_toilet', true);
            })->count();
        }

        $recentReports = Flag::query()
            ->with(['flagger:id,name,full_name', 'flaggable'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($flag) => [
                'id' => $flag->id,
                'reason' => $flag->reason,
                'status' => $flag->status,
                'flagger' => $flag->flagger?->full_name ?? $flag->flagger?->name ?? null,
                'target' => is_object($flag->flaggable) ? ($flag->flaggable?->name ?? class_basename($flag->flaggable_type)) : class_basename($flag->flaggable_type),
                'created_at' => $flag->created_at?->toIso8601String(),
            ]);

        $recentTutorials = Tutorial::query()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($tutorial) => [
                'id' => $tutorial->id,
                'title' => $tutorial->title,
                'category' => $tutorial->category,
                'is_published' => (bool) $tutorial->is_published,
                'created_at' => $tutorial->created_at?->toIso8601String(),
            ]);

        // ── Recent completed requests ──
        $recentCompleted = HelpRequest::query()
            ->with(['requester:id,name,full_name', 'volunteer:id,name,full_name'])
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get()
            ->map(fn ($hr) => [
                'id' => $hr->id,
                'assistance_type' => $hr->assistance_type,
                'service_fee' => $hasPaymentColumns ? round((int) $hr->service_fee / 100, 2) : 0,
                'platform_fee' => $hasFeeColumns ? round((int) $hr->fee_amount_cents / 100, 2) : 0,
                'payment_method' => $hasPaymentColumns ? $hr->payment_method : 'cash',
                'requester' => $hr->requester?->full_name ?? $hr->requester?->name ?? "User #{$hr->requester_id}",
                'volunteer' => $hr->volunteer?->full_name ?? $hr->volunteer?->name ?? null,
                'completed_at' => $hr->completed_at?->toIso8601String(),
            ]);

        return response()->json([
            'counts' => [
                'locations' => $totalLocations,
                'categories' => Category::query()->count(),
                'pending_place_submissions' => $pendingSubmissions,
                'open_flags' => $openFlags,
                'pending_help_requests' => $pendingHelpRequests,
                'unread_notifications' => $unreadNotifications,
                'total_users' => $totalUsers,
                'total_volunteers' => $totalVolunteers,
                'pending_volunteers' => $pendingVolunteers,
                'total_help_requests' => $totalHelpRequests,
                'active_help_requests' => $activeHelpRequests,
                'completed_help_requests' => $completedHelpRequests,
                'cancelled_help_requests' => $cancelledHelpRequests,
            ],
            'revenue' => [
                'currency' => 'EGP',
                'gross_total' => round($grossRevenueCents / 100, 2),
                'platform_fees' => round($platformFeesCents / 100, 2),
                'volunteer_payouts' => round($netVolunteerPayCents / 100, 2),
                'fee_percentage' => (int) config('athar.platform_fee_percentage', 30),
                'by_method' => [
                    'cash' => ['count' => $cashRequests, 'amount' => round($cashRevenueCents / 100, 2)],
                    'card' => ['count' => $cardRequests, 'amount' => round($cardRevenueCents / 100, 2)],
                ],
            ],
            'reviews' => [
                'total' => $totalReviews,
                'average_rating' => $avgRating,
                'distribution' => $ratingDistribution,
            ],
            'tutorials_summary' => $tutorialsSummary,
            'reports_summary' => $reportsSummary,
            'submissions_summary' => $submissionsSummary,
            'accessibility_overview' => $accessibilityOverview,
            'help_request_status_breakdown' => $statusBreakdown,
            'assistance_types' => $assistanceTypes,
            'monthly_trends' => $monthlyTrends,
            'weekly_activity' => $weeklyActivity,
            'top_volunteers' => $topVolunteers,
            'recent_reports' => $recentReports,
            'recent_tutorials' => $recentTutorials,
            'recent_completed' => $recentCompleted,
        ]);
    }

    private function monthlyTrends(bool $hasFeeColumns): array
    {
        $start = Carbon::now()->subMonths(5)->startOfMonth();

        $selectCols = ['created_at', 'status', 'service_fee'];
        if ($hasFeeColumns) {
            $selectCols[] = 'fee_amount_cents';
        }

        $requests = HelpRequest::query()
            ->where('created_at', '>=', $start)
            ->get($selectCols);

        $users = User::query()
            ->where('role', '!=', 'admin')
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'role']);

        $grouped = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $start->copy()->addMonths($i)->format('Y-m');
            $grouped[$m] = ['month' => $m, 'requests' => 0, 'completed' => 0, 'revenue' => 0, 'new_users' => 0, 'new_volunteers' => 0];
        }

        foreach ($requests as $r) {
            $m = $r->created_at->format('Y-m');
            if (!isset($grouped[$m])) continue;
            $grouped[$m]['requests']++;
            if ($r->status === 'completed') {
                $grouped[$m]['completed']++;
                if ($hasFeeColumns) {
                    $grouped[$m]['revenue'] += round((int) $r->fee_amount_cents / 100, 2);
                } else {
                    $grouped[$m]['revenue'] += round((int) $r->service_fee / 100, 2);
                }
            }
        }

        foreach ($users as $u) {
            $m = $u->created_at->format('Y-m');
            if (!isset($grouped[$m])) continue;
            if ($u->role === 'volunteer') {
                $grouped[$m]['new_volunteers']++;
            } else {
                $grouped[$m]['new_users']++;
            }
        }

        return array_values($grouped);
    }

    private function weeklyActivity(): array
    {
        $result = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i);
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $newRequests = HelpRequest::query()->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $completed = HelpRequest::query()->where('status', 'completed')->whereBetween('completed_at', [$dayStart, $dayEnd])->count();
            $newPlaces = Location::query()->whereBetween('created_at', [$dayStart, $dayEnd])->count();

            $result[] = [
                'label' => $day->format('D'),
                'date' => $day->toDateString(),
                'requests' => $newRequests,
                'completed' => $completed,
                'places' => $newPlaces,
            ];
        }
        return $result;
    }

    private function topVolunteers(bool $hasReviewsTable, bool $hasFeeColumns): array
    {
        try {
            return User::query()
                ->where('role', 'volunteer')
                ->whereNotNull('role_verified_at')
                ->withCount(['helpRequestsAccepted as completed_count' => function ($q) {
                    $q->where('status', 'completed');
                }])
                ->having('completed_count', '>', 0)
                ->orderByDesc('completed_count')
                ->limit(5)
                ->get(['id', 'name', 'full_name', 'email'])
                ->map(function ($v) use ($hasReviewsTable, $hasFeeColumns) {
                    $avgRating = $hasReviewsTable
                        ? VolunteerReview::query()->where('volunteer_id', $v->id)->avg('rating')
                        : null;

                    $totalEarnings = $hasFeeColumns
                        ? (int) HelpRequest::query()->where('volunteer_id', $v->id)->where('status', 'completed')->sum('net_amount_cents')
                        : (int) HelpRequest::query()->where('volunteer_id', $v->id)->where('status', 'completed')->sum('service_fee');

                    return [
                        'id' => $v->id,
                        'name' => $v->full_name ?? $v->name,
                        'email' => $v->email,
                        'completed_requests' => (int) $v->completed_count,
                        'avg_rating' => round((float) $avgRating, 1),
                        'total_earnings' => round($totalEarnings / 100, 2),
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('Dashboard: topVolunteers query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
