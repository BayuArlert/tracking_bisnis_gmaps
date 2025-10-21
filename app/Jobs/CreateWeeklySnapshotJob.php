<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Business;
use App\Models\BusinessSnapshot;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateWeeklySnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting weekly snapshot creation job');

        try {
            $snapshotDate = Carbon::now()->startOfWeek();
            $businesses = Business::all();
            $snapshotsCreated = 0;

            foreach ($businesses as $business) {
                // Check if snapshot already exists for this week
                $existingSnapshot = BusinessSnapshot::where('business_id', $business->id)
                    ->where('snapshot_date', $snapshotDate)
                    ->first();

                if ($existingSnapshot) {
                    continue; // Skip if snapshot already exists
                }

                // Create new snapshot
                BusinessSnapshot::create([
                    'business_id' => $business->id,
                    'snapshot_date' => $snapshotDate,
                    'review_count' => $business->review_count,
                    'rating' => $business->rating,
                    'photo_count' => count($business->photo_metadata ?? []),
                    'indicators' => $business->indicators,
                ]);

                $snapshotsCreated++;
            }

            Log::info('Weekly snapshot creation job completed', [
                'snapshot_date' => $snapshotDate->toDateString(),
                'snapshots_created' => $snapshotsCreated,
                'total_businesses' => $businesses->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Weekly snapshot creation job failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Weekly snapshot creation job failed permanently', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
