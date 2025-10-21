<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ScrapeSession;
use App\Models\BusinessSnapshot;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupOldSessionsJob implements ShouldQueue
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
        Log::info('Starting cleanup job for old sessions and snapshots');

        try {
            $cutoffDate = Carbon::now()->subMonths(3);
            
            // Clean up old scrape sessions (older than 3 months)
            $oldSessions = ScrapeSession::where('started_at', '<', $cutoffDate)->get();
            $sessionsDeleted = 0;

            foreach ($oldSessions as $session) {
                $session->delete();
                $sessionsDeleted++;
            }

            // Clean up old business snapshots (older than 6 months)
            $snapshotCutoffDate = Carbon::now()->subMonths(6);
            $oldSnapshots = BusinessSnapshot::where('snapshot_date', '<', $snapshotCutoffDate)->get();
            $snapshotsDeleted = 0;

            foreach ($oldSnapshots as $snapshot) {
                $snapshot->delete();
                $snapshotsDeleted++;
            }

            Log::info('Cleanup job completed', [
                'sessions_deleted' => $sessionsDeleted,
                'snapshots_deleted' => $snapshotsDeleted,
                'cutoff_date' => $cutoffDate->toDateString(),
                'snapshot_cutoff_date' => $snapshotCutoffDate->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Cleanup job failed: ' . $e->getMessage(), [
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
        Log::error('Cleanup job failed permanently', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
