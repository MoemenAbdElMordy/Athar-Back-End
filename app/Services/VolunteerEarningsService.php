<?php

namespace App\Services;

use App\Models\HelpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VolunteerEarningsService
{
    /**
     * Build the full Earnings tab payload for a volunteer.
     */
    public function earnings(int $volunteerId): array
    {
        $feePct = (int) config('athar.platform_fee_percentage', 30);

        $totals = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->where('service_fee', '>', 0)
            ->selectRaw('SUM(service_fee) as gross, SUM(fee_amount_cents) as fees, SUM(net_amount_cents) as net')
            ->first();

        $gross = (int) ($totals->gross ?? 0);
        $fees = (int) ($totals->fees ?? 0);
        $net = (int) ($totals->net ?? 0);

        // Cleared: completed + cleared_at is set
        $cleared = (int) HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->where('service_fee', '>', 0)
            ->whereNotNull('cleared_at')
            ->sum('net_amount_cents');

        $pending = $net - $cleared;
        if ($pending < 0) {
            $pending = 0;
        }

        return [
            'summary' => [
                'currency' => 'EGP',
                'gross_earnings' => round($gross / 100, 2),
                'platform_fees' => round($fees / 100, 2),
                'net_earnings' => round($net / 100, 2),
                'pending_clearance' => round($pending / 100, 2),
                'cleared_earnings' => round($cleared / 100, 2),
                'service_fee_percentage' => $feePct,
            ],
            'fee_info' => [
                'title' => 'Athar Service Fee',
                'description' => "Athar keeps {$feePct}% from each completed paid request.",
            ],
            'monthly_net_earnings' => $this->monthlyNetEarnings($volunteerId),
        ];
    }

    /**
     * Net earnings per month for the last 6 months.
     * Uses PHP grouping instead of DATE_FORMAT for SQLite compatibility.
     */
    private function monthlyNetEarnings(int $volunteerId, int $months = 6): array
    {
        $start = Carbon::now()->subMonths($months - 1)->startOfMonth();

        $requests = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->where('service_fee', '>', 0)
            ->where('completed_at', '>=', $start)
            ->get(['completed_at', 'net_amount_cents']);

        // Group by month in PHP (DB-engine agnostic)
        $grouped = [];
        foreach ($requests as $r) {
            if ($r->completed_at) {
                $m = $r->completed_at->format('Y-m');
                $grouped[$m] = ($grouped[$m] ?? 0) + (int) $r->net_amount_cents;
            }
        }

        $result = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i)->format('Y-m');
            $result[] = [
                'month' => $m,
                'amount' => round((int) ($grouped[$m] ?? 0) / 100, 2),
            ];
        }

        return $result;
    }
}
