<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessibilityContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'user_id' => $this->user_id,
            'wide_entrance' => (bool) $this->wide_entrance,
            'wheelchair_accessible' => (bool) $this->wheelchair_accessible,
            'elevator_available' => (bool) $this->elevator_available,
            'ramp_available' => (bool) $this->ramp_available,
            'parking' => (bool) $this->parking,
            'accessible_toilet' => (bool) $this->accessible_toilet,
            'notes' => $this->notes,
            'status' => $this->status,
            'pending_verification' => $this->status !== 'verified',
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
