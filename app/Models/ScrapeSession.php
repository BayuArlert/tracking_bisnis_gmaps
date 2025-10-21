<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ScrapeSession extends Model
{
    protected $fillable = [
        'session_type',
        'target_area',
        'target_categories',
        'started_at',
        'completed_at',
        'api_calls_count',
        'estimated_cost',
        'businesses_found',
        'businesses_new',
        'businesses_updated',
        'status',
        'error_log',
    ];

    protected $casts = [
        'target_categories' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_cost' => 'decimal:4',
    ];

    /**
     * Mark session as completed
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark session as failed
     */
    public function markFailed(?string $error = null): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_log' => $error,
        ]);
    }

    /**
     * Get duration of the session
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * Get cost per business found
     */
    public function getCostPerBusinessAttribute(): float
    {
        if ($this->businesses_found === 0) {
            return 0;
        }

        return $this->estimated_cost / $this->businesses_found;
    }
}
