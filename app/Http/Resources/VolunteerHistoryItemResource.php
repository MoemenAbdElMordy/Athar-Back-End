<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerHistoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requester = $this->requester;
        $name = $requester?->name ?? 'Unknown';
        $initials = $this->initials($name);

        $durationMinutes = null;
        if ($this->accepted_at && $this->completed_at) {
            $durationMinutes = (int) $this->accepted_at->diffInMinutes($this->completed_at);
        }

        return [
            'id' => $this->id,
            'help_request_id' => $this->id,
            'user' => [
                'id' => $requester?->id,
                'name' => $name,
                'initials' => $initials,
            ],
            'status' => $this->status,
            'service_date' => optional($this->completed_at ?? $this->accepted_at)->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'assistance_type' => $this->assistance_type,
            'gross_amount' => round($this->service_fee / 100, 2),
            'fee_amount' => round($this->fee_amount_cents / 100, 2),
            'net_amount' => round($this->net_amount_cents / 100, 2),
            'currency' => 'EGP',
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $initials ?: '??';
    }
}
