<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSnapshot extends Model
{
    protected $fillable = [
        'business_id',
        'snapshot_date',
        'review_count',
        'rating',
        'photo_count',
        'indicators',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'indicators' => 'array',
    ];

    /**
     * Get the business that owns the snapshot.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
