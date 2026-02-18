<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'icon',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function placeSubmissions(): HasMany
    {
        return $this->hasMany(PlaceSubmission::class);
    }
}
