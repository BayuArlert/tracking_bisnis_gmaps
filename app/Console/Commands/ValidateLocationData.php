<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use App\Services\LocationParserService;

class ValidateLocationData extends Command
{
    protected $signature = 'location:validate {--sample=20 : Number of sample addresses to show}';
    protected $description = 'Validate location parsing accuracy from business addresses';

    private LocationParserService $locationParser;

    public function __construct(LocationParserService $locationParser)
    {
        parent::__construct();
        $this->locationParser = $locationParser;
    }

    public function handle()
    {
        $this->info('🔍 Validating Location Data Parsing...');
        $this->newLine();

        // Get sample businesses with addresses
        $sampleSize = (int) $this->option('sample');
        $businesses = Business::whereNotNull('address')
            ->where('address', '!=', '')
            ->limit($sampleSize)
            ->get(['id', 'name', 'address', 'area']);

        $totalBusinesses = Business::count();
        $businessesWithAddress = Business::whereNotNull('address')
            ->where('address', '!=', '')
            ->count();

        $this->info("📊 Database Statistics:");
        $this->line("  Total businesses: " . number_format($totalBusinesses));
        $this->line("  With address: " . number_format($businessesWithAddress) . " (" . 
                   round(($businessesWithAddress / $totalBusinesses) * 100, 1) . "%)");
        $this->newLine();

        // Parse sample addresses
        $parsingStats = [
            'kabupaten' => 0,
            'kecamatan' => 0,
            'desa' => 0,
            'total_parsed' => 0,
        ];

        $this->info("🔍 Parsing Sample Addresses:");
        $this->line("Sample size: {$sampleSize} addresses");
        $this->newLine();

        foreach ($businesses as $index => $business) {
            $locationData = $this->locationParser->parseLocationHierarchy($business->address);
            $validation = $this->locationParser->validateLocationData($locationData);

            // Count successful parsing
            if ($locationData['kabupaten']) $parsingStats['kabupaten']++;
            if ($locationData['kecamatan']) $parsingStats['kecamatan']++;
            if ($locationData['desa']) $parsingStats['desa']++;
            if ($validation['is_valid']) $parsingStats['total_parsed']++;

            // Show sample results
            $this->line(($index + 1) . ". " . $business->name);
            $this->line("   Address: " . $business->address);
            $this->line("   Parsed: Kabupaten={$locationData['kabupaten']}, " .
                       "Kecamatan={$locationData['kecamatan']}, Desa={$locationData['desa']}");
            $this->line("   Confidence: {$validation['confidence']}% " . 
                       ($validation['is_valid'] ? '✅' : '❌'));
            
            if (!empty($validation['issues'])) {
                $this->line("   Issues: " . implode(', ', $validation['issues']));
            }
            $this->newLine();
        }

        // Show parsing statistics
        $this->info("📈 Parsing Statistics:");
        $this->line("  Kabupaten parsed: {$parsingStats['kabupaten']}/{$sampleSize} " . 
                   "(" . round(($parsingStats['kabupaten'] / $sampleSize) * 100, 1) . "%)");
        $this->line("  Kecamatan parsed: {$parsingStats['kecamatan']}/{$sampleSize} " . 
                   "(" . round(($parsingStats['kecamatan'] / $sampleSize) * 100, 1) . "%)");
        $this->line("  Desa parsed: {$parsingStats['desa']}/{$sampleSize} " . 
                   "(" . round(($parsingStats['desa'] / $sampleSize) * 100, 1) . "%)");
        $this->line("  Complete parsing: {$parsingStats['total_parsed']}/{$sampleSize} " . 
                   "(" . round(($parsingStats['total_parsed'] / $sampleSize) * 100, 1) . "%)");

        // Show unique values found
        $this->newLine();
        $this->info("🏷️  Unique Values Found:");
        
        $uniqueKabupaten = collect();
        $uniqueKecamatan = collect();
        $uniqueDesa = collect();

        foreach ($businesses as $business) {
            $locationData = $this->locationParser->parseLocationHierarchy($business->address);
            
            if ($locationData['kabupaten']) {
                $uniqueKabupaten->push($locationData['kabupaten']);
            }
            if ($locationData['kecamatan']) {
                $uniqueKecamatan->push($locationData['kecamatan']);
            }
            if ($locationData['desa']) {
                $uniqueDesa->push($locationData['desa']);
            }
        }

        $this->line("  Unique Kabupaten: " . $uniqueKabupaten->unique()->count());
        $this->line("  Unique Kecamatan: " . $uniqueKecamatan->unique()->count());
        $this->line("  Unique Desa: " . $uniqueDesa->unique()->count());

        // Show some examples
        if ($uniqueKabupaten->unique()->isNotEmpty()) {
            $this->line("  Sample Kabupaten: " . $uniqueKabupaten->unique()->take(5)->implode(', '));
        }
        if ($uniqueKecamatan->unique()->isNotEmpty()) {
            $this->line("  Sample Kecamatan: " . $uniqueKecamatan->unique()->take(5)->implode(', '));
        }
        if ($uniqueDesa->unique()->isNotEmpty()) {
            $this->line("  Sample Desa: " . $uniqueDesa->unique()->take(5)->implode(', '));
        }

        $this->newLine();
        $this->info("✅ Validation complete!");

        return 0;
    }
}
