<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Government extends Model
{
    protected $fillable = [
        'accessible_locations',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
