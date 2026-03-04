<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacySetting extends Model
{
    protected $fillable = [
        'user_id',
        'location_sharing',
        'profile_visibility',
        'show_ratings',
        'activity_status',
        'two_factor_auth',
    ];

    protected function casts(): array
    {
        return [
            'location_sharing' => 'boolean',
            'profile_visibility' => 'boolean',
            'show_ratings' => 'boolean',
            'activity_status' => 'boolean',
            'two_factor_auth' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
