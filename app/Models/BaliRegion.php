<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaliRegion extends Model
{
    protected $fillable = [
        'type',
        'name',
        'parent_id',
        'center_lat',
        'center_lng',
        'search_radius',
        'priority',
    ];

    protected $casts = [
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
    ];

    /**
     * Get the parent region (for kecamatan/desa)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(BaliRegion::class, 'parent_id');
    }

    /**
     * Get child regions (kecamatan for kabupaten, desa for kecamatan)
     */
    public function children(): HasMany
    {
        return $this->hasMany(BaliRegion::class, 'parent_id');
    }

    /**
     * Get all descendants (recursive)
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Scope for kabupaten only
     */
    public function scopeKabupaten($query)
    {
        return $query->where('type', 'kabupaten');
    }

    /**
     * Scope for kecamatan only
     */
    public function scopeKecamatan($query)
    {
        return $query->where('type', 'kecamatan');
    }

    /**
     * Scope for desa only
     */
    public function scopeDesa($query)
    {
        return $query->where('type', 'desa');
    }

    /**
     * Scope ordered by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * Get full hierarchy name (e.g., "Desa Canggu, Kecamatan Kuta Utara, Kabupaten Badung")
     */
    public function getFullNameAttribute(): string
    {
        $parts = [$this->name];
        
        $parent = $this->parent;
        while ($parent) {
            $parts[] = $parent->name;
            $parent = $parent->parent;
        }
        
        return implode(', ', $parts);
    }
}
