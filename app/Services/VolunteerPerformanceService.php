<?php

namespace App\Services;

use App\Models\HelpRequest;
use App\Models\VolunteerReview;

class VolunteerPerformanceService
{
    public function __construct(
        private readonly VolunteerAnalyticsService $analyticsService,
    ) {}

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

        $totalReviews = (int) ($reviewStats->total ?? 0);
        $avgRating = round((float) ($reviewStats->avg_r ?? 0), 1);
        $positiveReviews = (int) ($reviewStats->positive ?? 0);
        $fiveStarRatings = (int) ($reviewStats->five_star ?? 0);

        // On-time rate: percentage of assigned requests that were completed (vs cancelled after assignment).
        $assignedCompleted = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->where('status', 'completed')
            ->whereNotNull('accepted_at')
            ->count();

        $assignedTotal = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->whereIn('status', ['completed', 'cancelled'])
            ->whereNotNull('accepted_at')
            ->count();

        $onTimeRate = $assignedTotal > 0
            ? round($assignedCompleted / $assignedTotal * 100, 1)
            : ($completed > 0 ? 100.0 : 0.0);

        // Response rate: how many assigned (accepted) requests out of total requests offered.
        // We approximate "offered" as all requests where this volunteer was assigned (accepted + cancelled + completed).
        $totalOffered = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->count();
        $totalAccepted = HelpRequest::query()
            ->where('volunteer_id', $volunteerId)
            ->whereNotNull('accepted_at')
            ->count();
        $responseRate = $totalOffered > 0
            ? round($totalAccepted / $totalOffered * 100, 1)
            : ($completed > 0 ? 100.0 : 0.0);

        // Completion rate: completed out of all assigned (accepted) requests.
        $completionRate = $totalAccepted > 0
            ? round($completed / $totalAccepted * 100, 1)
            : ($completed > 0 ? 100.0 : 0.0);

        // Grade based on completed count and rating
        $grade = match (true) {
            $completed >= 50 && $avgRating >= 4.5 => 'S',
            $completed >= 20 && $avgRating >= 4.0 => 'A',
            $completed >= 10 => 'B',
            $completed >= 5 => 'C',
            $completed > 0 => 'D',
            default => 'D',
        };

        $headline = $completed > 0 ? 'Performance summary' : 'No performance data yet';

        // Percentile: rank this volunteer among all volunteers by completed count.
        $allVolunteerCompletedCounts = HelpRequest::query()
            ->where('status', 'completed')
            ->select('volunteer_id')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('volunteer_id')
            ->pluck('cnt');
        $totalVolunteers = $allVolunteerCompletedCounts->count();
        $belowCount = $allVolunteerCompletedCounts->filter(fn ($c) => $c < $completed)->count();
        $percentile = $totalVolunteers > 0
            ? (int) round($belowCount / $totalVolunteers * 100)
            : 0;

        $badgeObjects = $this->deriveBadges($volunteerId, $completed, $avgRating, $onTimeRate);
        $badges = array_map(fn ($b) => $b['title'], $badgeObjects);

        // Flat structure matching Kotlin parsePerformance field names
        return [
            'grade' => $grade,
            'headline' => $headline,
            'percentile' => $percentile,
            'response_rate' => $responseRate,
            'completion_rate' => $completionRate,
            'average_rating' => $avgRating,
            'on_time_rate' => $onTimeRate,
            'completed' => $completed,
            'pending' => $pending,
            'users_helped' => $usersHelped,
            'positive_reviews' => $positiveReviews,
            'five_star_ratings' => $fiveStarRatings,
            'total_reviews' => $totalReviews,
            'badges' => $badges,
            'weekly_activity' => $this->analyticsService->weeklyActivity($volunteerId),
            'request_types' => $this->analyticsService->requestTypesDistribution($volunteerId),
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
