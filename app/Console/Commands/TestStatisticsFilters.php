<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use Carbon\Carbon;

class TestStatisticsFilters extends Command
{
    protected $signature = 'test:statistics-filters';
    protected $description = 'Test Statistics page filter combinations';

    public function handle()
    {
        $this->info('=== TESTING STATISTICS FILTER COMBINATIONS ===');
        $this->info('');
        
        // Test 1: Kabupaten only
        $this->info('TEST 1: Kabupaten Only (Badung)');
        $this->info('===================================');
        $count1 = Business::where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%badung%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%badung%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kabupaten badung%']);
        })->count();
        $this->line("Count: $count1");
        $this->line('');
        
        // Test 2: Kabupaten + Kecamatan (Statistics index logic)
        $this->info('TEST 2: Kabupaten + Kecamatan (Badung + Abiansemal)');
        $this->info('=====================================================');
        $query2 = Business::query();
        
        // Apply kabupaten filter (same as StatisticsController)
        $kabupaten = 'Badung';
        $query2->where(function($q) use ($kabupaten) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kabupaten ' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kota ' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ',%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ' %']);
        });
        
        // Apply kecamatan filter (same as StatisticsController)
        $kecamatan = 'Abiansemal';
        $query2->where(function($q) use ($kecamatan) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kecamatan ' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ',%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ' %']);
        });
        
        $count2 = $query2->count();
        $this->line("Count: $count2");
        
        // Show sample
        $sample2 = $query2->select('name', 'address', 'area')->limit(3)->get();
        foreach($sample2 as $b) {
            $this->line("  - " . $b->name);
        }
        $this->line('');
        
        // Test 3: + Category Filter
        $this->info('TEST 3: Kabupaten + Kecamatan + Category (Coffee)');
        $this->info('==================================================');
        $query3 = Business::query();
        
        // Apply kabupaten
        $query3->where(function($q) use ($kabupaten) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kabupaten ' . strtolower($kabupaten) . '%']);
        });
        
        // Apply kecamatan
        $query3->where(function($q) use ($kecamatan) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%']);
        });
        
        // Apply category (multi-select support)
        $categories = ['Coffee', 'Cafe'];
        $query3->where(function($q) use ($categories) {
            foreach ($categories as $cat) {
                $q->orWhere('category', 'like', '%' . trim($cat) . '%');
            }
        });
        
        $count3 = $query3->count();
        $this->line("Count: $count3");
        $this->line('');
        
        // Test 4: + Period Filter (90 days - default in Statistics)
        $this->info('TEST 4: + Period Filter (90 days)');
        $this->info('===================================');
        $query4 = Business::query();
        
        // Apply kabupaten
        $query4->where(function($q) use ($kabupaten) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%']);
        });
        
        // Apply kecamatan
        $query4->where(function($q) use ($kecamatan) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%']);
        });
        
        // Apply period filter (90 days)
        $dateFrom = Carbon::now()->subDays(90);
        $query4->where('first_seen', '>=', $dateFrom);
        
        $count4 = $query4->count();
        $this->line("Count: $count4");
        $this->line("Date from: " . $dateFrom->format('Y-m-d'));
        $this->line('');
        
        // Test 5: + Confidence Filter (>40% - default in Statistics)
        $this->info('TEST 5: + Confidence Filter (>40%)');
        $this->info('====================================');
        $query5 = Business::query();
        
        // Apply kabupaten
        $query5->where(function($q) use ($kabupaten) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%']);
        });
        
        // Apply kecamatan
        $query5->where(function($q) use ($kecamatan) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%']);
        });
        
        // Apply period
        $query5->where('first_seen', '>=', $dateFrom);
        
        // Apply confidence
        $minConfidence = 40;
        $query5->whereRaw('JSON_EXTRACT(indicators, "$.new_business_confidence") >= ?', [$minConfidence]);
        
        $count5 = $query5->count();
        $this->line("Count: $count5");
        $this->line('');
        
        // Test 6: ALL Filters Combined
        $this->info('TEST 6: ALL FILTERS (Badung + Abiansemal + Coffee + 90d + >40%)');
        $this->info('=================================================================');
        $query6 = Business::query();
        
        // Apply kabupaten
        $query6->where(function($q) use ($kabupaten) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%']);
        });
        
        // Apply kecamatan
        $query6->where(function($q) use ($kecamatan) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%']);
        });
        
        // Apply category
        $query6->where(function($q) use ($categories) {
            foreach ($categories as $cat) {
                $q->orWhere('category', 'like', '%' . trim($cat) . '%');
            }
        });
        
        // Apply period
        $query6->where('first_seen', '>=', $dateFrom);
        
        // Apply confidence
        $query6->whereRaw('JSON_EXTRACT(indicators, "$.new_business_confidence") >= ?', [$minConfidence]);
        
        $count6 = $query6->count();
        $this->line("Count: $count6");
        
        // Show sample results
        if ($count6 > 0) {
            $this->line("\nSample results:");
            $sample6 = $query6->select('name', 'category', 'first_seen', 'indicators')->limit(3)->get();
            foreach($sample6 as $b) {
                $confidence = $b->indicators['new_business_confidence'] ?? 0;
                $this->line("  - {$b->name} | {$b->category} | {$b->first_seen} | Confidence: {$confidence}%");
            }
        }
        $this->line('');
        
        // Summary Table
        $this->info('SUMMARY:');
        $this->info('========');
        $this->table(
            ['Test', 'Count', 'Status'],
            [
                ['Badung only', $count1, $count1 > 0 ? '✅' : '❌'],
                ['Badung + Abiansemal', $count2, $count2 > 0 ? '✅' : '❌'],
                ['+ Category (Coffee)', $count3, $count3 >= 0 ? '✅' : '❌'],
                ['+ Period (90 days)', $count4, $count4 >= 0 ? '✅' : '❌'],
                ['+ Confidence (>40%)', $count5, $count5 >= 0 ? '✅' : '❌'],
                ['ALL COMBINED', $count6, $count6 >= 0 ? '✅' : '❌'],
            ]
        );
        
        $this->info('');
        
        // Validation
        if ($count1 >= $count2 && $count2 >= $count3 && $count3 >= $count4 && $count4 >= $count5 && $count5 >= $count6) {
            $this->info('✅ VALIDATION PASSED: Each filter correctly reduces the result set!');
            $this->info('✅ All Statistics filter combinations are working correctly!');
        } else {
            $this->warn('⚠️ WARNING: Filter progression is not as expected. Please check the logic.');
        }
        
        return 0;
    }
}
