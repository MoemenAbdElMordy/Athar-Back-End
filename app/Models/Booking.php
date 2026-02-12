<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function companion(): BelongsTo
    {
        return $this->belongsTo(Companion::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
