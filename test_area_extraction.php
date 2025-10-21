<?php

$address = 'M5MM+R65, Jalan Raya, Batunya, Kec. Baturiti, Kabupaten Tabanan, Bali 82191, Indonesia';

echo "Testing area extraction...\n";
echo "Address: $address\n\n";

$parts = array_map('trim', explode(',', $address));
echo "All parts:\n";
foreach ($parts as $i => $part) {
    echo "[$i] $part\n";
}

echo "\nLooking for 'Kota' or 'Kabupaten':\n";
foreach ($parts as $part) {
    if (str_contains($part, 'Kota') || str_contains($part, 'Kabupaten')) {
        echo "Found: $part\n";
    }
}

echo "\nWhat would be returned: ";
foreach ($parts as $part) {
    if (str_contains($part, 'Kota') || str_contains($part, 'Kabupaten')) {
        echo $part . "\n";
        break;
    }
}

// Test fallback
$fallback = $parts[count($parts) - 2] ?? $address;
echo "Fallback result: $fallback\n";
