<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the Overview / Impact array produced by VolunteerAnalyticsService.
 * Passes the data through as-is because the service already shapes it.
 */
class VolunteerImpactResource extends JsonResource
{
    /**
     * The resource wraps a plain array, not an Eloquent model.
     */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
