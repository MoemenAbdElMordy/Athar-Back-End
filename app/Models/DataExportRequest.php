<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataExportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'categories',
        'format',
        'status',
        'download_url',
        'expires_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'expires_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
