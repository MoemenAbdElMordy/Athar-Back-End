<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpRequest extends Model
{
    protected $fillable = [
        'requester_id',
        'volunteer_id',
        'user_id',
        'assigned_admin_id',
        'status',
        'urgency_level',
        'assistance_type',
        'details',
        'name',
        'phone',
        'location_text',
        'lat',
        'lng',
        'message',
        'from_name',
        'from_lat',
        'from_lng',
        'to_name',
        'to_lat',
        'to_lng',
        'accepted_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
