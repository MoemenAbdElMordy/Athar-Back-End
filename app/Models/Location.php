<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    public function government(): BelongsTo
    {
        return $this->belongsTo(Government::class);
    }

    public function accessibilityReport(): HasOne
    {
        return $this->hasOne(AccessibilityReport::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
