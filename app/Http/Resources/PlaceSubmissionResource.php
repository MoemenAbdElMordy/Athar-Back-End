<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => (float) $this->lat,
            'longitude' => (float) $this->lng,
            'category_id' => $this->category_id,
            'notes' => $this->notes,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => optional($this->reviewed_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
