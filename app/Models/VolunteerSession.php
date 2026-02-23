<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerSession extends Model
{
    protected $fillable = [
        'user_id',
        'is_live',
        'started_at',
        'ended_at',
        'last_lat',
        'last_lng',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
