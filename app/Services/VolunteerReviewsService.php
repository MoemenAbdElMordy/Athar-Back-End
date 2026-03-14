<?php

namespace App\Services;

use App\Models\VolunteerReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VolunteerReviewsService
{
    /**
     * Build the Reviews tab summary (distribution + averages).
     */
    public function summary(int $volunteerId): array
    {
        $stats = VolunteerReview::query()
            ->where('volunteer_id', $volunteerId)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_r')
            ->first();

        $dist = VolunteerReview::query()
            ->where('volunteer_id', $volunteerId)
            ->selectRaw('rating, COUNT(*) as cnt')
            ->groupBy('rating')
            ->pluck('cnt', 'rating');

        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $distribution[(string) $star] = (int) ($dist[$star] ?? 0);
        }

        return [
            'average_rating' => round((float) ($stats->avg_r ?? 0), 1),
            'total_reviews' => (int) ($stats->total ?? 0),
            'distribution' => $distribution,
        ];
    }

    /**
     * Paginated list of reviews for a volunteer.
     */
    public function paginated(int $volunteerId, int $perPage = 10, ?int $ratingFilter = null): LengthAwarePaginator
    {
        $query = VolunteerReview::query()
            ->where('volunteer_id', $volunteerId)
            ->with('reviewer')
            ->latest();

        if ($ratingFilter !== null) {
            $query->where('rating', $ratingFilter);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
