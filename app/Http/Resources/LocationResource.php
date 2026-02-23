<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'government_id' => $this->government_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'icon' => $this->category?->icon,
            ]),
            'distance_km' => isset($this->distance_km) ? round((float) $this->distance_km, 2) : null,
            'rating_avg' => round((float) ($this->average_rating ?? 0), 2),
            'ratings_count' => (int) ($this->reviews_count ?? 0),
            'accessibility' => [
                'verified_report' => $this->whenLoaded('accessibilityReport', function () {
                    if (!$this->accessibilityReport) {
                        return null;
                    }

                    return [
                        'verified' => (bool) $this->accessibilityReport->verified,
                        'wide_entrance' => (bool) $this->accessibilityReport->wide_entrance,
                        'wheelchair_accessible' => (bool) $this->accessibilityReport->wheelchair_accessible,
                        'elevator_available' => (bool) $this->accessibilityReport->elevator_available,
                        'ramp_available' => (bool) $this->accessibilityReport->ramp_available,
                        'parking' => (bool) $this->accessibilityReport->parking,
                        'accessible_toilet' => (bool) ($this->accessibilityReport->accessible_toilet ?? false),
                        'notes' => $this->accessibilityReport->notes,
                    ];
                }),
                'contributions_summary' => [
                    'count' => (int) ($this->accessibility_contributions_count ?? 0),
                    'wide_entrance' => (bool) ($this->contributions_wide_entrance ?? false),
                    'wheelchair_accessible' => (bool) ($this->contributions_wheelchair_accessible ?? false),
                    'elevator_available' => (bool) ($this->contributions_elevator_available ?? false),
                    'ramp_available' => (bool) ($this->contributions_ramp_available ?? false),
                    'parking' => (bool) ($this->contributions_parking ?? false),
                    'accessible_toilet' => (bool) ($this->contributions_accessible_toilet ?? false),
                ],
            ],
        ];
    }
}
