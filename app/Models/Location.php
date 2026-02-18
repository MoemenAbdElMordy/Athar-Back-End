<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'government_id',
        'latitude',
        'longitude',
        'category_id',
    ];

    public function government(): BelongsTo
    {
        return $this->belongsTo(Government::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function accessibilityReport(): HasOne
    {
        return $this->hasOne(AccessibilityReport::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}
