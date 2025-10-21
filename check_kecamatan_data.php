<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Business;

echo "Checking kecamatan data in businesses...\n\n";

// Check businesses with "Kec." in address
$businessesWithKec = Business::whereNotNull('address')
    ->where('address', 'LIKE', '%Kec.%')
    ->select('name', 'address', 'area')
    ->limit(10)
    ->get();

echo "Businesses with 'Kec.' in address:\n";
foreach($businessesWithKec as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Address: " . $b->address . "\n";
    echo "Area: " . $b->area . "\n";
    echo "---\n";
}

// Check businesses with "Baturiti" in address
$baturitiBusinesses = Business::whereNotNull('address')
    ->where('address', 'LIKE', '%Baturiti%')
    ->select('name', 'address', 'area')
    ->limit(5)
    ->get();

echo "\nBusinesses with 'Baturiti' in address:\n";
foreach($baturitiBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Address: " . $b->address . "\n";
    echo "Area: " . $b->area . "\n";
    echo "---\n";
}

// Check businesses with "Kecamatan" in address
$kecamatanBusinesses = Business::whereNotNull('address')
    ->where('address', 'LIKE', '%Kecamatan%')
    ->select('name', 'address', 'area')
    ->limit(5)
    ->get();

echo "\nBusinesses with 'Kecamatan' in address:\n";
foreach($kecamatanBusinesses as $b) {
    echo "Name: " . $b->name . "\n";
    echo "Address: " . $b->address . "\n";
    echo "Area: " . $b->area . "\n";
    echo "---\n";
}

// Count totals
$totalKec = Business::whereNotNull('address')->where('address', 'LIKE', '%Kec.%')->count();
$totalBaturiti = Business::whereNotNull('address')->where('address', 'LIKE', '%Baturiti%')->count();
$totalKecamatan = Business::whereNotNull('address')->where('address', 'LIKE', '%Kecamatan%')->count();

echo "\nCounts:\n";
echo "With 'Kec.': $totalKec\n";
echo "With 'Baturiti': $totalBaturiti\n";
echo "With 'Kecamatan': $totalKecamatan\n";
