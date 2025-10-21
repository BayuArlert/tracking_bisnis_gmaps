<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $fillable = [
        'place_id',
        'name',
        'category',
        'types',
        'address',
        'phone',
        'website',
        'area',
        'lat',
        'lng',
        'rating',
        'review_count',
        'first_seen',
        'last_fetched',
        'indicators',
        'google_maps_url',
        'opening_hours',
        'price_level',
        'photo_metadata',
        'review_metadata',
        'scraped_count',
        'last_update_type',
    ];

    protected $casts = [
        'indicators' => 'array',
        'types' => 'array',
        'opening_hours' => 'array',
        'photo_metadata' => 'array',
        'review_metadata' => 'array',
        'first_seen' => 'datetime',
        'last_fetched' => 'datetime',
    ];

    /**
     * Get the snapshots for this business
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(BusinessSnapshot::class);
    }

    /**
     * Get the latest snapshot
     */
    public function latestSnapshot()
    {
        return $this->hasOne(BusinessSnapshot::class)->latest('snapshot_date');
    }

    /**
     * Get new business confidence score from indicators
     */
    public function getNewBusinessConfidenceAttribute(): int
    {
        return $this->indicators['new_business_confidence'] ?? 0;
    }

    /**
     * Check if business is recently opened
     */
    public function getIsRecentlyOpenedAttribute(): bool
    {
        return $this->indicators['recently_opened'] ?? false;
    }

    /**
     * Get business age estimate from indicators
     */
    public function getBusinessAgeEstimateAttribute(): string
    {
        return $this->indicators['metadata_analysis']['business_age_estimate'] ?? 'unknown';
    }

    /**
     * Scope for new businesses (confidence score > 40)
     */
    public function scopeNewBusinesses($query)
    {
        return $query->whereJsonContains('indicators->new_business_confidence', '>', 40);
    }

    /**
     * Scope for recently opened businesses
     */
    public function scopeRecentlyOpened($query)
    {
        return $query->whereJsonContains('indicators->recently_opened', true);
    }

    /**
     * Scope for specific business age
     */
    public function scopeByAge($query, string $age)
    {
        return $query->whereJsonContains('indicators->metadata_analysis->business_age_estimate', $age);
    }

    /**
     * Scope for businesses with specific types
     */
    public function scopeByTypes($query, array $types)
    {
        return $query->where(function ($q) use ($types) {
            foreach ($types as $type) {
                $q->orWhereJsonContains('types', $type);
            }
        });
    }
}
