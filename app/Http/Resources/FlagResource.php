<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->flaggable_id,
            'type' => $this->reason,
            'details' => $this->details,
            'status' => $this->status,
            'admin_note' => $this->admin_note,
            'handled_at' => optional($this->handled_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
