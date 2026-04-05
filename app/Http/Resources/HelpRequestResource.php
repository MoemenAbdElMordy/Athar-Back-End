<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HelpRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requester_id' => $this->requester_id,
            'volunteer_id' => $this->volunteer_id,
            'requester' => [
                'id' => $this->requester?->id,
                'name' => $this->requester?->name,
                'phone' => $this->requester?->phone,
                'profile_photo_path' => $this->requester?->profile_photo_path,
            ],
            'volunteer' => [
                'id' => $this->volunteer?->id,
                'name' => $this->volunteer?->name,
                'phone' => $this->volunteer?->phone,
                'profile_photo_path' => $this->volunteer?->profile_photo_path,
            ],
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'service_fee' => $this->service_fee,
            'service_fee_egp' => $this->service_fee > 0 ? round($this->service_fee / 100, 2) : 0,
            'hours' => (int) ($this->hours ?: 1),
            'price_per_hour' => (int) ($this->price_per_hour ?? 0),
            'price_per_hour_egp' => $this->price_per_hour > 0 ? round($this->price_per_hour / 100, 2) : 0,
            'payment_status' => $this->whenLoaded('payment', fn () => $this->payment?->status, null),
            'assistance_type' => $this->assistance_type,
            'urgency' => $this->urgency_level,
            'details' => $this->details,
            'from_label' => $this->from_name,
            'from_lat' => (float) $this->from_lat,
            'from_lng' => (float) $this->from_lng,
            'to_label' => $this->to_name,
            'to_lat' => $this->to_lat !== null ? (float) $this->to_lat : null,
            'to_lng' => $this->to_lng !== null ? (float) $this->to_lng : null,
            'distance_km' => isset($this->distance_km) ? round((float) $this->distance_km, 2) : null,
            'requested_ago' => optional($this->created_at)?->diffForHumans(),
            'completed_ago' => optional($this->completed_at)?->diffForHumans(),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'channels' => [
                'requester' => 'private-user.'.$this->requester_id,
                'volunteer' => $this->volunteer_id ? 'private-volunteer.'.$this->volunteer_id : null,
                'live_volunteers' => 'private-volunteers.live',
            ],
        ];
    }
}
