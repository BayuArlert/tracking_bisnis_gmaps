<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ScrapingOrchestratorService;
use App\Models\BaliRegion;

class ScrapeInitialCommand extends Command
{
    protected $signature = 'scrape:initial {area} {category?} {--timeout=30}';
    protected $description = 'Start initial scraping for a specific area and category';

    public function handle(ScrapingOrchestratorService $scrapingOrchestrator)
    {
        $area = $this->argument('area');
        $category = $this->argument('category');
        $timeout = (int) $this->option('timeout');
        
        $categories = $category ? [$category] : [];
        
        // Set execution time limit
        set_time_limit($timeout * 60); // Convert minutes to seconds
        
        $this->info("🚀 Starting scraping for {$area}...");
        if ($categories) {
            $this->info("📂 Categories: " . implode(', ', $categories));
        } else {
            $this->info("📂 Categories: All categories");
        }
        $this->info("⏱️  Timeout: {$timeout} minutes");
        
        // Check if area exists
        $regions = BaliRegion::where('type', 'kabupaten')
            ->where(function($query) use ($area) {
                $query->where('name', $area)
                      ->orWhere('name', 'LIKE', "{$area} - %");
            })
            ->get();
            
        if ($regions->isEmpty()) {
            $this->error("❌ Area '{$area}' not found!");
            $this->info("Available areas:");
            $availableAreas = BaliRegion::where('type', 'kabupaten')
                ->pluck('name')
                ->unique()
                ->toArray();
            foreach ($availableAreas as $availableArea) {
                $this->line("  - {$availableArea}");
            }
            return 1;
        }
        
        $this->info("✅ Found " . $regions->count() . " zone(s) for {$area}");
        
        try {
            $startTime = time();
            
            $this->info("⏳ Starting scraping process...");
            $this->newLine();
            
            // Start scraping session (this is BLOCKING and will take 5-10 minutes)
            $session = $scrapingOrchestrator->startInitialScraping($area, $categories);
            
            $this->newLine();
            $this->info("✅ Scraping completed successfully!");
            $this->info("🆔 Session ID: {$session->id}");
            $this->showResults($session);
            
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("❌ Failed: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function showResults($session)
    {
        $this->newLine();
        $this->info("📈 Final Results:");
        $this->info("  🏢 Businesses Found: {$session->businesses_found}");
        $this->info("  🆕 New Businesses: {$session->businesses_new}");
        $this->info("  📞 API Calls: {$session->api_calls_count}");
        $this->info("  💰 Estimated Cost: $" . number_format($session->estimated_cost, 2));
        $this->info("  ⏱️  Duration: " . ($session->duration ?? 'Unknown') . " seconds");
        $this->info("  📊 Status: {$session->status}");
    }
}
