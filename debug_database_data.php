<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Business;

echo "=== DEBUGGING DATABASE DATA ===\n\n";

// Check sample businesses with their addresses
echo "1. SAMPLE BUSINESSES WITH ADDRESSES:\n";
echo "=====================================\n";
$businesses = Business::whereNotNull('address')
    ->whereNotNull('area')
    ->select('name', 'address', 'area')
    ->limit(10)
    ->get();

foreach($businesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Address: " . $b->address . "\n";
    echo "Area: " . $b->area . "\n";
    echo "---\n";
}

// Check businesses with "Tabanan" in address or area
echo "\n2. BUSINESSES WITH 'TABANAN':\n";
echo "==============================\n";
$tabananBusinesses = Business::where(function($q) {
    $q->where('address', 'LIKE', '%Tabanan%')
      ->orWhere('area', 'LIKE', '%Tabanan%');
})->select('name', 'address', 'area')->limit(5)->get();

foreach($tabananBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Address: " . $b->address . "\n";
    echo "Area: " . $b->area . "\n";
    echo "---\n";
}

// Check businesses with "Baturiti" in address or area
echo "\n3. BUSINESSES WITH 'BATURITI':\n";
echo "===============================\n";
$baturitiBusinesses = Business::where(function($q) {
    $q->where('address', 'LIKE', '%Baturiti%')
      ->orWhere('area', 'LIKE', '%Baturiti%');
})->select('name', 'address', 'area')->limit(5)->get();

foreach($baturitiBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Address: " . $b->address . "\n";
    echo "Area: " . $b->area . "\n";
    echo "---\n";
}

// Check businesses with "Kec." in address
echo "\n4. BUSINESSES WITH 'KEC.' IN ADDRESS:\n";
echo "======================================\n";
$kecBusinesses = Business::where('address', 'LIKE', '%Kec.%')
    ->select('name', 'address', 'area')
    ->limit(5)
    ->get();

foreach($kecBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Address: " . $b->address . "\n";
    echo "Area: " . $b->area . "\n";
    echo "---\n";
}

// Check businesses with "Kecamatan" in address
echo "\n5. BUSINESSES WITH 'KECAMATAN' IN ADDRESS:\n";
echo "===========================================\n";
$kecamatanBusinesses = Business::where('address', 'LIKE', '%Kecamatan%')
    ->select('name', 'address', 'area')
    ->limit(5)
    ->get();

foreach($kecamatanBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Address: " . $b->address . "\n";
    echo "Area: " . $b->area . "\n";
    echo "---\n";
}

// Count totals
echo "\n6. COUNTS:\n";
echo "==========\n";
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

echo "Total with 'Tabanan': $totalTabanan\n";
echo "Total with 'Baturiti': $totalBaturiti\n";
echo "Total with 'Kec.': $totalKec\n";
echo "Total with 'Kecamatan': $totalKecamatan\n";

// Check what's in the area field
echo "\n7. UNIQUE AREA VALUES (first 20):\n";
echo "===================================\n";
$uniqueAreas = Business::select('area')
    ->whereNotNull('area')
    ->where('area', '!=', '')
    ->distinct()
    ->limit(20)
    ->pluck('area');

foreach($uniqueAreas as $area) {
    echo "- $area\n";
}
