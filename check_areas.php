<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Business;

echo "=== AREAS IN DATABASE ===\n";
echo "Total businesses: " . Business::count() . "\n\n";

$areas = Business::select('area')
    ->distinct()
    ->whereNotNull('area')
    ->where('area', '!=', '')
    ->orderBy('area')
    ->pluck('area');

echo "Unique areas found: " . $areas->count() . "\n\n";

echo "First 30 areas:\n";
foreach ($areas->take(30) as $area) {
    echo "- " . $area . "\n";
}

echo "\nAreas with numbers:\n";
foreach ($areas as $area) {
    if (preg_match('/\d+/', $area)) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Bali':\n";
foreach ($areas as $area) {
    if (stripos($area, 'bali') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Badung':\n";
foreach ($areas as $area) {
    if (stripos($area, 'badung') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Kuta':\n";
foreach ($areas as $area) {
    if (stripos($area, 'kuta') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Canggu':\n";
foreach ($areas as $area) {
    if (stripos($area, 'canggu') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Seminyak':\n";
foreach ($areas as $area) {
    if (stripos($area, 'seminyak') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Jimbaran':\n";
foreach ($areas as $area) {
    if (stripos($area, 'jimbaran') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Nusa Dua':\n";
foreach ($areas as $area) {
    if (stripos($area, 'nusa dua') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Sanur':\n";
foreach ($areas as $area) {
    if (stripos($area, 'sanur') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Uluwatu':\n";
foreach ($areas as $area) {
    if (stripos($area, 'uluwatu') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Mengwi':\n";
foreach ($areas as $area) {
    if (stripos($area, 'mengwi') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Abiansemal':\n";
foreach ($areas as $area) {
    if (stripos($area, 'abiansemal') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\nAreas containing 'Petang':\n";
foreach ($areas as $area) {
    if (stripos($area, 'petang') !== false) {
        echo "- " . $area . "\n";
    }
}

echo "\n=== NON-BALI AREAS ===\n";
$nonBaliAreas = [];
foreach ($areas as $area) {
    $lowerArea = strtolower($area);
    if (strpos($lowerArea, 'bali') === false && 
        strpos($lowerArea, 'badung') === false &&
        strpos($lowerArea, 'kuta') === false &&
        strpos($lowerArea, 'canggu') === false &&
        strpos($lowerArea, 'seminyak') === false &&
        strpos($lowerArea, 'jimbaran') === false &&
        strpos($lowerArea, 'nusa dua') === false &&
        strpos($lowerArea, 'sanur') === false &&
        strpos($lowerArea, 'uluwatu') === false &&
        strpos($lowerArea, 'mengwi') === false &&
        strpos($lowerArea, 'abiansemal') === false &&
        strpos($lowerArea, 'petang') === false) {
        $nonBaliAreas[] = $area;
    }
}

echo "Non-Bali areas found: " . count($nonBaliAreas) . "\n";
foreach ($nonBaliAreas as $area) {
    echo "- " . $area . "\n";
}

echo "\n=== DONE ===\n";
