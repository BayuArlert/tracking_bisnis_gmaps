<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\WeeklyScrapeJob;
use App\Jobs\CreateWeeklySnapshotJob;
use App\Jobs\CleanupOldSessionsJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // DISABLED: Weekly scraping (Sunday at 2:00 AM) - Use manual scraping only
        // $schedule->job(new WeeklyScrapeJob())
        //          ->weeklyOn(0, '02:00')
        //          ->withoutOverlapping()
        //          ->name('weekly-scraping')
        //          ->description('Weekly scraping for new businesses');

        // Weekly snapshot creation (Monday at 1:00 AM)
        $schedule->job(new CreateWeeklySnapshotJob())
                 ->weeklyOn(1, '01:00')
                 ->withoutOverlapping()
                 ->name('weekly-snapshot')
                 ->description('Create weekly snapshots of business data');

        // Daily cleanup of old sessions and snapshots (Daily at 3:00 AM)
        $schedule->job(new CleanupOldSessionsJob())
                 ->dailyAt('03:00')
                 ->withoutOverlapping()
                 ->name('daily-cleanup')
                 ->description('Clean up old sessions and snapshots');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
