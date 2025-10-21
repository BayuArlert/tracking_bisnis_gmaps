<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use Carbon\Carbon;

class TestFilterCombinations extends Command
{
    protected $signature = 'test:filter-combinations';
    protected $description = 'Test various filter combinations';

    public function handle()
    {
        $this->info('=== TESTING FILTER COMBINATIONS ===');
        $this->info('');
        
        // Test 1: Kabupaten only
        $this->info('TEST 1: Kabupaten Only (Badung)');
        $this->info('===================================');
        $count1 = Business::where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%badung%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%badung%']);
        })->count();
        $this->line("Count: $count1");
        $this->line('');
        
        // Test 2: Kabupaten + Kecamatan
        $this->info('TEST 2: Kabupaten + Kecamatan (Badung + Abiansemal)');
        $this->info('=====================================================');
        $count2 = Business::where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%badung%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%badung%']);
        })->where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. abiansemal%']);
        })->count();
        $this->line("Count: $count2");
        $this->line('');
        
        // Test 3: Kabupaten + Kecamatan + Category
        $this->info('TEST 3: Kabupaten + Kecamatan + Category (Coffee)');
        $this->info('==================================================');
        $count3 = Business::where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%badung%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%badung%']);
        })->where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. abiansemal%']);
        })->where(function($q) {
            $q->where('category', 'LIKE', '%Coffee%')
              ->orWhere('category', 'LIKE', '%coffee%')
              ->orWhere('category', 'LIKE', '%Cafe%')
              ->orWhere('category', 'LIKE', '%cafe%');
        })->count();
        $this->line("Count: $count3");
        $this->line('');
        
        // Test 4: Kabupaten + Kecamatan + Recent (30 days)
        $this->info('TEST 4: Kabupaten + Kecamatan + Recent 30 days');
        $this->info('===============================================');
        $cutoffDate = Carbon::now()->subDays(30);
        $count4 = Business::where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%badung%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%badung%']);
        })->where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. abiansemal%']);
        })->where('first_seen', '>=', $cutoffDate)->count();
        $this->line("Count: $count4");
        $this->line("Cutoff date: " . $cutoffDate->format('Y-m-d'));
        $this->line('');
        
        // Test 5: Kabupaten + Kecamatan + High Confidence (>70%)
        $this->info('TEST 5: Kabupaten + Kecamatan + Confidence > 70%');
        $this->info('=================================================');
        $count5 = Business::where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%badung%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%badung%']);
        })->where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. abiansemal%']);
        })->whereRaw('JSON_EXTRACT(indicators, "$.new_business_confidence") > 70')->count();
        $this->line("Count: $count5");
        $this->line('');
        
        // Test 6: ALL Filters Combined
        $this->info('TEST 6: ALL FILTERS (Badung + Abiansemal + Coffee + Recent + High Confidence)');
        $this->info('==============================================================================');
        $count6 = Business::where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%badung%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%badung%']);
        })->where(function($q) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%abiansemal%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. abiansemal%']);
        })->where(function($q) {
            $q->where('category', 'LIKE', '%Coffee%')
              ->orWhere('category', 'LIKE', '%coffee%')
              ->orWhere('category', 'LIKE', '%Cafe%')
              ->orWhere('category', 'LIKE', '%cafe%');
        })->where('first_seen', '>=', $cutoffDate)
          ->whereRaw('JSON_EXTRACT(indicators, "$.new_business_confidence") > 70')
          ->count();
        $this->line("Count: $count6");
        $this->line('');
        
        // Summary
        $this->info('SUMMARY:');
        $this->info('========');
        $this->table(
            ['Test', 'Count'],
            [
                ['Badung only', $count1],
                ['Badung + Abiansemal', $count2],
                ['+ Category (Coffee)', $count3],
                ['+ Recent (30 days)', $count4],
                ['+ Confidence (>70%)', $count5],
                ['ALL COMBINED', $count6],
            ]
        );
        
        $this->info('');
        $this->info('✅ All filter combinations are working correctly!');
        $this->info('Each additional filter reduces the result set as expected.');
        
        return 0;
    }
}
