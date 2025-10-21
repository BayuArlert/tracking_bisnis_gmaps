<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ScrapingOrchestratorService;
use App\Services\GooglePlacesService;
use Illuminate\Support\Facades\Log;

class WeeklyScrapeJob implements ShouldQueue
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
    public function handle(ScrapingOrchestratorService $scrapingOrchestrator, GooglePlacesService $googlePlacesService): void
    {
        Log::info('Starting weekly scraping job');

        try {
            // Check if we're approaching budget limit
            if ($googlePlacesService->isApproachingBudgetLimit(300.0, 0.9)) {
                Log::warning('Approaching budget limit, skipping weekly scraping');
                return;
            }

            // Get remaining budget
            $remainingBudget = $googlePlacesService->getRemainingBudget(300.0);
            Log::info("Remaining budget: \${$remainingBudget}");

            // If remaining budget is too low, skip this week
            if ($remainingBudget < 20) {
                Log::warning('Insufficient budget for weekly scraping, skipping');
                return;
            }

            // Start weekly update scraping
            $session = $scrapingOrchestrator->startWeeklyUpdate();

            Log::info('Weekly scraping job completed', [
                'session_id' => $session->id,
                'businesses_found' => $session->businesses_found,
                'businesses_new' => $session->businesses_new,
                'estimated_cost' => $session->estimated_cost,
            ]);

        } catch (\Exception $e) {
            Log::error('Weekly scraping job failed: ' . $e->getMessage(), [
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
        Log::error('Weekly scraping job failed permanently', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
