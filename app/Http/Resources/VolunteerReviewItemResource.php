<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerReviewItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reviewer = $this->reviewer;
        $name = $reviewer?->name ?? 'Anonymous';
        $initials = $this->initials($name);

        return [
            'id' => $this->id,
            'reviewer' => [
                'id' => $reviewer?->id,
                'name' => $name,
                'initials' => $initials,
            ],
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'date' => optional($this->created_at)->toDateString(),
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
