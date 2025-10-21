<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;

class DebugBusinessData extends Command
{
    protected $signature = 'debug:business-data';
    protected $description = 'Debug business data for filtering issues';

    public function handle()
    {
        $this->info('=== DEBUGGING BUSINESS DATA ===');
        
        // Sample businesses
        $this->info("\n1. SAMPLE BUSINESSES:");
        $this->info("=====================");
        $businesses = Business::select('name', 'address', 'area')->limit(5)->get();
        foreach($businesses as $b) {
            $this->line("Name: " . $b->name);
            $this->line("Address: " . $b->address);
            $this->line("Area: " . $b->area);
            $this->line("---");
        }
        
        // Check Tabanan
        $this->info("\n2. BUSINESSES WITH 'TABANAN':");
        $this->info("=============================");
        $tabananBusinesses = Business::where(function($q) {
            $q->where('address', 'LIKE', '%Tabanan%')
              ->orWhere('area', 'LIKE', '%Tabanan%');
        })->select('name', 'address', 'area')->limit(5)->get();
        
        foreach($tabananBusinesses as $b) {
            $this->line("Name: " . $b->name);
            $this->line("Address: " . $b->address);
            $this->line("Area: " . $b->area);
            $this->line("---");
        }
        
        // Check Baturiti
        $this->info("\n3. BUSINESSES WITH 'BATURITI':");
        $this->info("==============================");
        $baturitiBusinesses = Business::where(function($q) {
            $q->where('address', 'LIKE', '%Baturiti%')
              ->orWhere('area', 'LIKE', '%Baturiti%');
        })->select('name', 'address', 'area')->limit(5)->get();
        
        foreach($baturitiBusinesses as $b) {
            $this->line("Name: " . $b->name);
            $this->line("Address: " . $b->address);
            $this->line("Area: " . $b->area);
            $this->line("---");
        }
        
        // Check Kec.
        $this->info("\n4. BUSINESSES WITH 'KEC.' IN ADDRESS:");
        $this->info("=====================================");
        $kecBusinesses = Business::where('address', 'LIKE', '%Kec.%')
            ->select('name', 'address', 'area')
            ->limit(5)
            ->get();
        
        foreach($kecBusinesses as $b) {
            $this->line("Name: " . $b->name);
            $this->line("Address: " . $b->address);
            $this->line("Area: " . $b->area);
            $this->line("---");
        }
        
        // Count totals
        $this->info("\n5. COUNTS:");
        $this->info("==========");
        $totalTabanan = Business::where(function($q) {
            $q->where('address', 'LIKE', '%Tabanan%')
              ->orWhere('area', 'LIKE', '%Tabanan%');
        })->count();
        
        $totalBaturiti = Business::where(function($q) {
            $q->where('address', 'LIKE', '%Baturiti%')
              ->orWhere('area', 'LIKE', '%Baturiti%');
        })->count();
        
        $totalKec = Business::where('address', 'LIKE', '%Kec.%')->count();
        $totalKecamatan = Business::where('address', 'LIKE', '%Kecamatan%')->count();
        
        $this->line("Total with 'Tabanan': $totalTabanan");
        $this->line("Total with 'Baturiti': $totalBaturiti");
        $this->line("Total with 'Kec.': $totalKec");
        $this->line("Total with 'Kecamatan': $totalKecamatan");
        
        // Check unique area values
        $this->info("\n6. UNIQUE AREA VALUES (first 20):");
        $this->info("==================================");
        $uniqueAreas = Business::select('area')
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->distinct()
            ->limit(20)
            ->pluck('area');
        
        foreach($uniqueAreas as $area) {
            $this->line("- $area");
        }
        
        return 0;
    }
}