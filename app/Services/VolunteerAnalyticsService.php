<?php

namespace App\Services;

use App\Models\HelpRequest;
use App\Models\VolunteerReview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VolunteerAnalyticsService
{
    /**
     * Build the full Overview / Impact payload for a volunteer.
     */
    public function overview(int $volunteerId): array
    {
        $completedBase = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed');

        $completedCount = (clone $completedBase)->count();

        $reviewStats = VolunteerReview::query()
            ->where('volunteer_id', $volunteerId)
            ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg_rating')
            ->first();

        $avgRating = round((float) ($reviewStats->avg_rating ?? 0), 1);
        $reviewsCount = (int) ($reviewStats->cnt ?? 0);

        $pendingCount = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->whereIn('status', ['active', 'pending_payment', 'confirmed'])
            ->count();

        $netAllTime = $this->netEarningsCents($volunteerId);

        return [
            'summary' => [
                'net_earnings_all_time' => round($netAllTime / 100, 2),
                'currency' => 'EGP',
                'completed_requests_count' => $completedCount,
                'avg_rating' => $avgRating,
                'reviews_count' => $reviewsCount,
                'pending_requests_count' => $pendingCount,
            ],
            'this_month' => $this->thisMonthSummary($volunteerId),
            'weekly_activity' => $this->weeklyActivity($volunteerId),
            'request_types' => $this->requestTypesDistribution($volunteerId),
        ];
    }

    /**
     * Current-month net earnings + change vs last month.
     */
    public function thisMonthSummary(int $volunteerId): array
    {
        $now = Carbon::now();
        $thisMonthNet = $this->netEarningsCentsForMonth($volunteerId, $now->year, $now->month);
        $lastMonthNet = $this->netEarningsCentsForMonth(
            $volunteerId,
            $now->copy()->subMonth()->year,
            $now->copy()->subMonth()->month,
        );

        $change = $lastMonthNet > 0
            ? round(($thisMonthNet - $lastMonthNet) / $lastMonthNet * 100, 1)
            : ($thisMonthNet > 0 ? 100.0 : 0.0);

        return [
            'month' => $now->format('Y-m'),
            'net_earnings' => round($thisMonthNet / 100, 2),
            'change_percentage_vs_last_month' => $change,
        ];
    }

    /**
     * Completed requests per day for the last 7 days (rolling).
     *
     * Includes compatibility aliases used by older/mobile clients.
     */
    public function weeklyActivity(int $volunteerId): array
    {
        $today = Carbon::now();
        $activity = $this->weeklyActivityForEndDate($volunteerId, $today);

        $totalInWindow = array_sum(array_map(
            static fn (array $day): int => (int) ($day['completed_requests'] ?? 0),
            $activity,
        ));

        if ($totalInWindow > 0) {
            return $activity;
        }

        $latestRequest = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNotNull('completed_at')
                    ->orWhereNotNull('updated_at');
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('updated_at')
            ->first(['completed_at', 'updated_at']);

        $latestActivityAt = $latestRequest?->completed_at ?? $latestRequest?->updated_at;

        if (!$latestActivityAt) {
            return $activity;
        }

        return $this->weeklyActivityForEndDate($volunteerId, Carbon::parse($latestActivityAt));
    }

    private function weeklyActivityForEndDate(int $volunteerId, Carbon $endDate): array
    {
        $start = $endDate->copy()->subDays(6)->startOfDay();
        $end = $endDate->copy()->endOfDay();

        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $endDate->copy()->subDays($i);
            $dateKey = $day->toDateString();

            $days[$dateKey] = [
                'label' => $day->format('D'),
                'day' => $day->format('D'),
                'date' => $dateKey,
                'completed_requests' => 0,
                'count' => 0,
                'net_earnings' => 0.0,
                'earnings' => 0.0,
            ];
        }

        $requests = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->where(function ($query) use ($start, $end) {
                $query
                    ->whereBetween('completed_at', [$start, $end])
                    ->orWhere(function ($fallback) use ($start, $end) {
                        $fallback->whereNull('completed_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            })
            ->get(['completed_at', 'updated_at', 'net_amount_cents', 'service_fee']);

        foreach ($requests as $request) {
            $completedAt = $request->completed_at ?? $request->updated_at;

            if (!$completedAt) {
                continue;
            }

            $dateKey = $completedAt->toDateString();

            if (!isset($days[$dateKey])) {
                continue;
            }

            $netAmountCents = (int) ($request->net_amount_cents ?? 0);
            if ($netAmountCents <= 0) {
                $netAmountCents = (int) ($request->service_fee ?? 0);
            }

            $days[$dateKey]['completed_requests']++;
            $days[$dateKey]['count']++;
            $days[$dateKey]['net_earnings'] = round($days[$dateKey]['net_earnings'] + ($netAmountCents / 100), 2);
            $days[$dateKey]['earnings'] = $days[$dateKey]['net_earnings'];
        }

        return array_values($days);
    }

    /**
     * Assistance-type distribution for completed requests.
     */
    public function requestTypesDistribution(int $volunteerId): array
    {
        $rows = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->selectRaw('assistance_type, COUNT(*) as cnt')
            ->groupBy('assistance_type')
            ->orderByDesc('cnt')
            ->get();

        $total = $rows->sum('cnt');

        $labelMap = [
            'wheelchair_assist' => 'Wheelchair Assist',
            'visual_guidance' => 'Visual Guidance',
            'hearing_support' => 'Hearing Support',
            'mobility_help' => 'Mobility Help',
            'navigation' => 'Navigation',
            'companionship' => 'Companionship',
        ];

        return $rows->map(function ($row) use ($total, $labelMap) {
            $pct = $total > 0 ? round($row->cnt / $total * 100, 1) : 0;
            return [
                'type' => $row->assistance_type,
                'label' => $labelMap[$row->assistance_type] ?? ucfirst(str_replace('_', ' ', $row->assistance_type)),
                'count' => (int) $row->cnt,
                'percentage' => $pct,
            ];
        })->values()->toArray();
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function netEarningsCents(int $volunteerId, ?string $from = null, ?string $to = null): int
    {
        $q = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->where('service_fee', '>', 0);

        if ($from) {
            $q->where(function ($query) use ($from) {
                $query
                    ->where('completed_at', '>=', $from)
                    ->orWhere(function ($fallback) use ($from) {
                        $fallback->whereNull('completed_at')
                            ->where('updated_at', '>=', $from);
                    });
            });
        }
        if ($to) {
            $q->where(function ($query) use ($to) {
                $query
                    ->where('completed_at', '<=', $to)
                    ->orWhere(function ($fallback) use ($to) {
                        $fallback->whereNull('completed_at')
                            ->where('updated_at', '<=', $to);
                    });
            });
        }

        return (int) $q->sum(DB::raw('CASE WHEN net_amount_cents > 0 THEN net_amount_cents ELSE service_fee END'));
    }

    private function netEarningsCentsForMonth(int $volunteerId, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return $this->netEarningsCents($volunteerId, $start->toDateTimeString(), $end->toDateTimeString());
    }
}
