<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'full_name',
        'email',
        'password',
        'password_changed_at',
        'phone',
        'city',
        'national_id',
        'date_of_birth',
        'id_document_path',
        'certification_document_path',
        'volunteer_languages',
        'volunteer_availability',
        'volunteer_motivation',
        'disability_type',
        'mobility_aids',
        'profile_photo_path',
        'assistance_needs',
        'emergency_contact_name',
        'emergency_contact_phone',
        'role',
        'role_locked',
        'role_verified_at',
        'is_active',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function helpRequestsMade(): HasMany
    {
        return $this->hasMany(HelpRequest::class, 'requester_id');
    }

    public function helpRequestsAccepted(): HasMany
    {
        return $this->hasMany(HelpRequest::class, 'volunteer_id');
    }

    public function volunteerSessions(): HasMany
    {
        return $this->hasMany(VolunteerSession::class);
    }

    public function messagesSent(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function messagesReceived(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function privacySetting(): HasOne
    {
        return $this->hasOne(PrivacySetting::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function dataExportRequests(): HasMany
    {
        return $this->hasMany(DataExportRequest::class);
    }

    public function placeSubmissions(): HasMany
    {
        return $this->hasMany(PlaceSubmission::class, 'submitted_by');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(Flag::class, 'flagger_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'date_of_birth' => 'date',
            'volunteer_languages' => 'array',
            'volunteer_availability' => 'array',
            'role_locked' => 'boolean',
            'is_active' => 'boolean',
            'role_verified_at' => 'datetime',
        ];
    }
}
