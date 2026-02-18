<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceSubmission extends Model
{
    protected $fillable = [
        'submitted_by',
        'name',
        'address',
        'lat',
        'lng',
        'category_id',
        'notes',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'rejection_reason',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }
}
