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

        $eventAt = $this->completed_at ?? $this->accepted_at ?? $this->updated_at ?? $this->created_at;

        // Use the stored requested hours (submitted by user at creation time).
        // Fall back to timestamp-based calculation only for legacy records without hours.
        $storedHours = (int) ($this->hours ?? 0);

        $durationMinutes = null;
        if ($this->accepted_at && $this->completed_at) {
            $durationMinutes = (int) $this->accepted_at->diffInMinutes($this->completed_at);
        } elseif ($this->started_at && $this->completed_at) {
            $durationMinutes = (int) $this->started_at->diffInMinutes($this->completed_at);
        }

        $hours = $storedHours;
        if ($hours <= 0 && $durationMinutes !== null && $durationMinutes > 0) {
            $hours = (int) ceil($durationMinutes / 60);
        }
        $hours = max($hours, 1);

        $netAmountCents = (int) ($this->net_amount_cents ?? 0);
        if ($netAmountCents <= 0) {
            $netAmountCents = (int) ($this->service_fee ?? 0);
        }

        return [
            'id' => $this->id,
            'help_request_id' => $this->id,
            'user' => [
                'id' => $requester?->id,
                'name' => $name,
                'initials' => $initials,
            ],
            'user_name' => $name,
            'status' => $this->status,
            'service_date' => optional($eventAt)->toIso8601String(),
            'completed_at' => optional($eventAt)->toIso8601String(),
            'request_date' => optional($eventAt)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'date' => optional($eventAt)->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'hours' => $hours,
            'assistance_type' => $this->assistance_type,
            'gross_amount' => round($this->service_fee / 100, 2),
            'fee_amount' => round($this->fee_amount_cents / 100, 2),
            'net_amount' => round($netAmountCents / 100, 2),
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
