<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Business;

// Simulate the cleanAreaName function
function cleanAreaName($area)
{
    // Remove numbers and extra spaces from area names
    // "Bali 80993" -> "Bali"
    $clean = preg_replace('/\s+\d+/', '', $area);
    $clean = trim($clean);
    
    // Handle specific cases based on ACTUAL DATA in database
    
    // If it's just numbers (postal codes), skip
    if (preg_match('/^\d+$/', $clean)) {
        return null;
    }
    
    // If contains "Kabupaten Badung", keep as is
    if (stripos($clean, 'Kabupaten Badung') !== false) {
        return 'Kabupaten Badung';
    }
    
    // If contains "Jimbaran", keep as is (found in data)
    if (stripos($clean, 'Jimbaran') !== false) {
        return 'Jimbaran';
    }
    
    // If contains "Sanur", keep as is (found in data)
    if (stripos($clean, 'Sanur') !== false) {
        return 'Sanur';
    }
    
    // If contains "Bali" (without specific area), map to "Bali"
    if (stripos($clean, 'Bali') !== false) {
        return 'Bali';
    }
    
    // If it's clearly not Bali/Badung, return null to filter out
    $nonBaliAreas = [
        'Jawa Timur', 'Jakarta', 'Surabaya', 'Bandung', 'Yogyakarta', 
        'Solo', 'Semarang', 'Malang', 'Medan', 'Palembang',
        'Makassar', 'Manado', 'Pontianak', 'Balikpapan',
        'Lombok', 'Flores', 'Sumba', 'Timor', 'Papua',
        'Kalimantan', 'Sumatra', 'Sulawesi', 'Nusa Tenggara',
        'West Java', 'Kota Bandung', 'Kota Semarang', 'Kota Denpasar',
        'Kabupaten Bangli', 'Kabupaten Buleleng', 'Kabupaten Gianyar',
        'Kabupaten Jember', 'Kabupaten Karangasem', 'Kabupaten Klungkung',
        'Kabupaten Sayan', 'Kabupaten Sigi', 'Kabupaten Tabanan'
    ];
    
    foreach ($nonBaliAreas as $nonBali) {
        if (stripos($clean, $nonBali) !== false) {
            return null; // Filter out non-Bali areas
        }
    }
    
    // If it's just "Kabupaten" or "Kota" without specific name, skip
    if (in_array($clean, ['Kabupaten', 'Kota'])) {
        return null;
    }
    
    // Default: keep the clean name if it looks reasonable
    return $clean;
}

echo "=== TESTING AREA CLEANING ===\n\n";

$areas = Business::select('area')
    ->distinct()
    ->whereNotNull('area')
    ->where('area', '!=', '')
    ->orderBy('area')
    ->pluck('area');

echo "Testing first 20 areas:\n";
$cleanedAreas = [];
foreach ($areas->take(20) as $area) {
    $cleaned = cleanAreaName($area);
    echo sprintf("%-20s -> %s\n", $area, $cleaned ?: 'NULL (filtered out)');
    if ($cleaned && !in_array($cleaned, $cleanedAreas)) {
        $cleanedAreas[] = $cleaned;
    }
}

echo "\nUnique cleaned areas found: " . count($cleanedAreas) . "\n";
echo "Cleaned areas: " . implode(', ', $cleanedAreas) . "\n";

echo "\n=== TESTING SPECIFIC CASES ===\n";
$testCases = [
    'Bali 80993',
    'Bali 80111',
    'Jimbaran',
    'Sanur',
    'Kabupaten Badung',
    'Jawa Timur 64133',
    'Kota Bandung',
    'Kabupaten Bangli',
    'Kota Denpasar',
    'West Java',
    '38351',
    '80351',
    'Bali',
    'Kabupaten',
    'Kota'
];

foreach ($testCases as $test) {
    $cleaned = cleanAreaName($test);
    echo sprintf("%-20s -> %s\n", $test, $cleaned ?: 'NULL (filtered out)');
}

echo "\n=== DONE ===\n";
