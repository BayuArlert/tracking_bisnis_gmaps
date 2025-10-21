<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use Illuminate\Http\Request;

class TestFiltering extends Command
{
    protected $signature = 'test:filtering {kabupaten?} {kecamatan?}';
    protected $description = 'Test business filtering with kabupaten and kecamatan';

    public function handle()
    {
        $kabupaten = $this->argument('kabupaten') ?? 'Tabanan';
        $kecamatan = $this->argument('kecamatan') ?? 'Baturiti';
        
        $this->info("=== TESTING FILTERING ===");
        $this->info("Kabupaten: $kabupaten");
        $this->info("Kecamatan: $kecamatan");
        $this->info("");
        
        // Test 1: Only kabupaten filter
        $this->info("1. ONLY KABUPATEN FILTER:");
        $this->info("=========================");
        $query1 = Business::query();
        $query1->where(function($q) use ($kabupaten) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kabupaten ' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kota ' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ',%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ' %']);
        });
        
        $count1 = $query1->count();
        $this->line("Count: $count1");
        
        $sample1 = $query1->select('name', 'address', 'area')->limit(3)->get();
        foreach($sample1 as $b) {
            $this->line("Name: " . $b->name);
            $this->line("Address: " . $b->address);
            $this->line("Area: " . $b->area);
            $this->line("---");
        }
        
        // Test 2: Only kecamatan filter
        $this->info("\n2. ONLY KECAMATAN FILTER:");
        $this->info("==========================");
        $query2 = Business::query();
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
        
        $sample2 = $query2->select('name', 'address', 'area')->limit(3)->get();
        foreach($sample2 as $b) {
            $this->line("Name: " . $b->name);
            $this->line("Address: " . $b->address);
            $this->line("Area: " . $b->area);
            $this->line("---");
        }
        
        // Test 3: Both filters (AND logic)
        $this->info("\n3. BOTH FILTERS (AND LOGIC):");
        $this->info("=============================");
        $query3 = Business::query();
        
        // Kabupaten filter
        $query3->where(function($q) use ($kabupaten) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kabupaten ' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kota ' . strtolower($kabupaten) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ',%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ' %']);
        });
        
        // Kecamatan filter
        $query3->where(function($q) use ($kecamatan) {
            $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kecamatan ' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ',%'])
              ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ' %']);
        });
        
        $count3 = $query3->count();
        $this->line("Count: $count3");
        
        $sample3 = $query3->select('name', 'address', 'area')->limit(3)->get();
        foreach($sample3 as $b) {
            $this->line("Name: " . $b->name);
            $this->line("Address: " . $b->address);
            $this->line("Area: " . $b->area);
            $this->line("---");
        }
        
        // Test 4: Manual check for specific case
        $this->info("\n4. MANUAL CHECK FOR SPECIFIC CASE:");
        $this->info("===================================");
        $manual = Business::where('address', 'LIKE', '%Kec. Baturiti%')
            ->where('address', 'LIKE', '%Kabupaten Tabanan%')
            ->select('name', 'address', 'area')
            ->limit(3)
            ->get();
            
        $manualCount = $manual->count();
        $this->line("Count: $manualCount");
        
        foreach($manual as $b) {
            $this->line("Name: " . $b->name);
            $this->line("Address: " . $b->address);
            $this->line("Area: " . $b->area);
            $this->line("---");
        }
        
        return 0;
    }
}
