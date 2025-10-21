<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Business;

echo "Checking Tabanan data...\n\n";

// Check for Tabanan
$tabananBusinesses = Business::where('area', 'LIKE', '%Tabanan%')
    ->select('name', 'area', 'address')
    ->limit(5)
    ->get();

echo "Businesses with 'Tabanan' in area:\n";
foreach($tabananBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Area: " . $b->area . "\n";
    echo "Address: " . $b->address . "\n";
    echo "---\n";
}

// Check for Baturiti
$baturitiBusinesses = Business::where('area', 'LIKE', '%Baturiti%')
    ->select('name', 'area', 'address')
    ->limit(5)
    ->get();

echo "\nBusinesses with 'Baturiti' in area:\n";
foreach($baturitiBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Area: " . $b->area . "\n";
    echo "Address: " . $b->address . "\n";
    echo "---\n";
}

// Check for "Luar Bali" tag
$luarBaliBusinesses = Business::where('area', 'LIKE', '%Luar Bali%')
    ->select('name', 'area', 'address')
    ->limit(5)
    ->get();

echo "\nBusinesses with 'Luar Bali' in area:\n";
foreach($luarBaliBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Area: " . $b->area . "\n";
    echo "Address: " . $b->address . "\n";
    echo "---\n";
}

// Check total count
$totalTabanan = Business::where('area', 'LIKE', '%Tabanan%')->count();
$totalBaturiti = Business::where('area', 'LIKE', '%Baturiti%')->count();
$totalLuarBali = Business::where('area', 'LIKE', '%Luar Bali%')->count();

echo "\nCounts:\n";
echo "Tabanan: $totalTabanan\n";
echo "Baturiti: $totalBaturiti\n";
echo "Luar Bali: $totalLuarBali\n";
