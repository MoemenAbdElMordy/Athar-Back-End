<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessibilityContribution extends Model
{
    protected $fillable = [
        'location_id',
        'user_id',
        'wide_entrance',
        'wheelchair_accessible',
        'elevator_available',
        'ramp_available',
        'parking',
        'accessible_toilet',
        'notes',
        'status',
        'verified_at',
        'verified_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'wide_entrance' => 'boolean',
            'wheelchair_accessible' => 'boolean',
            'elevator_available' => 'boolean',
            'ramp_available' => 'boolean',
            'parking' => 'boolean',
            'accessible_toilet' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_admin_id');
    }
}
