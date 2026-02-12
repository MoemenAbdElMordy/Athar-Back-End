<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessibilityReport extends Model
{
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
