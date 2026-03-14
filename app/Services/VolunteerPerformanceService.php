<?php

namespace App\Services;

use App\Models\HelpRequest;
use App\Models\VolunteerReview;

class VolunteerPerformanceService
{
    /**
     * Build the full Performance tab payload for a volunteer.
     */
    public function performance(int $volunteerId): array
    {
        $completed = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->count();

        $pending = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->whereIn('status', ['active', 'pending_payment', 'confirmed'])
            ->count();

        $usersHelped = (int) HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->distinct('requester_id')
            ->count('requester_id');

        $reviewStats = VolunteerReview::query()
            ->where('volunteer_id', $volunteerId)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_r, SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive, SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star')
            ->first();

        $avgRating = round((float) ($reviewStats->avg_r ?? 0), 1);
        $positiveReviews = (int) ($reviewStats->positive ?? 0);
        $fiveStarRatings = (int) ($reviewStats->five_star ?? 0);

        // On-time rate: percentage of assigned requests that were completed (vs cancelled after assignment).
        // TODO: refine with explicit SLA when available in schema.
        $assignedTotal = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->whereIn('status', ['completed', 'cancelled'])
            ->whereNotNull('accepted_at')
            ->count();

        $onTimeRate = $assignedTotal > 0
            ? round($completed / $assignedTotal * 100, 1)
            : 100.0;

        $badges = $this->deriveBadges($volunteerId, $completed, $avgRating, $onTimeRate);

        return [
            'metrics' => [
                'average_rating' => $avgRating,
                'average_rating_out_of' => 5,
                'on_time_rate' => $onTimeRate,
                'completed_requests' => $completed,
                'pending_requests' => $pending,
                'users_helped' => $usersHelped,
                'positive_reviews' => $positiveReviews,
                'five_star_ratings' => $fiveStarRatings,
                'badges_earned_count' => count($badges),
            ],
            'badges' => $badges,
        ];
    }

    /**
     * Derive badges dynamically based on volunteer stats.
     * No badge persistence table — calculated on-the-fly.
     */
    private function deriveBadges(int $volunteerId, int $completed, float $avgRating, float $onTimeRate): array
    {
        $badges = [];

        if ($avgRating >= 4.5) {
            $badges[] = ['code' => 'top_rated', 'title' => 'Top Rated'];
        }

        // Quick responder: average time from accepted_at to started_at (or completed_at) < 15 min
        // Uses (julianday(...) - julianday(...)) * 1440 for SQLite compat;
        // for MySQL use TIMESTAMPDIFF(MINUTE, ...) instead.
        $driver = HelpRequest::query()->getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $diffExpr = "(julianday(COALESCE(started_at, completed_at)) - julianday(accepted_at)) * 1440";
        } else {
            $diffExpr = "TIMESTAMPDIFF(MINUTE, accepted_at, COALESCE(started_at, completed_at))";
        }

        $avgResponseMinutes = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->whereNotNull('accepted_at')
            ->selectRaw("AVG({$diffExpr}) as avg_min")
            ->value('avg_min');

        if ($avgResponseMinutes !== null && (float) $avgResponseMinutes <= 15) {
            $badges[] = ['code' => 'quick_responder', 'title' => 'Quick Responder'];
        }

        if ($onTimeRate >= 95 && $completed >= 5) {
            $badges[] = ['code' => 'reliable', 'title' => 'Reliable'];
        }

        if ($completed >= 50) {
            $badges[] = ['code' => 'fifty_requests', 'title' => '50 Requests'];
        }

        if ($completed >= 10 && $avgRating >= 4.0 && $onTimeRate >= 90) {
            $badges[] = ['code' => 'community_hero', 'title' => 'Community Hero'];
        }

        return $badges;
    }
}
