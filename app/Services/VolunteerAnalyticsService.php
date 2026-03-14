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
     * Completed requests per day for the current week (Sat–Fri).
     */
    public function weeklyActivity(int $volunteerId): array
    {
        $labels = ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        // Start of week = Saturday
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::SATURDAY);
        $endOfWeek = $startOfWeek->copy()->addDays(7);

        $requests = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfWeek, $endOfWeek])
            ->get(['completed_at']);

        // Count per day-of-week using PHP (DB-engine agnostic)
        $counts = array_fill(0, 7, 0);
        foreach ($requests as $r) {
            if ($r->completed_at) {
                // Carbon dayOfWeek: 0=Sun,...,6=Sat → map to Sat-Fri index
                $carbonDow = $r->completed_at->dayOfWeek; // 0=Sun,...,6=Sat
                $satIndex = ($carbonDow + 1) % 7; // Sat=0,Sun=1,...,Fri=6
                $counts[$satIndex]++;
            }
        }

        $result = [];
        foreach ($labels as $i => $label) {
            $result[] = [
                'label' => $label,
                'completed_requests' => $counts[$i],
            ];
        }

        return $result;
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
            $q->where('completed_at', '>=', $from);
        }
        if ($to) {
            $q->where('completed_at', '<=', $to);
        }

        return (int) $q->sum('net_amount_cents');
    }

    private function netEarningsCentsForMonth(int $volunteerId, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return $this->netEarningsCents($volunteerId, $start->toDateTimeString(), $end->toDateTimeString());
    }
}
