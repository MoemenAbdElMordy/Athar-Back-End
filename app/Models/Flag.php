<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Flag extends Model
{
    protected $fillable = [
        'flagger_id',
        'flaggable_type',
        'flaggable_id',
        'reason',
        'details',
        'admin_note',
        'status',
        'handled_by_admin_id',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function flagger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagger_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_admin_id');
    }

    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }
}
