<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'push_enabled',
        'email_enabled',
        'sms_enabled',
        'volunteer_requests',
        'volunteer_accepted',
        'location_updates',
        'new_ratings',
        'community_updates',
        'marketing_emails',
        'sound_enabled',
        'vibration_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'volunteer_requests' => 'boolean',
            'volunteer_accepted' => 'boolean',
            'location_updates' => 'boolean',
            'new_ratings' => 'boolean',
            'community_updates' => 'boolean',
            'marketing_emails' => 'boolean',
            'sound_enabled' => 'boolean',
            'vibration_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
