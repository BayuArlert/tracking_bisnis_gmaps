<?php

// Simple debug without Laravel bootstrap
$host = 'localhost';
$dbname = 'tracking_bisnis_gmaps';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DATABASE CONNECTION SUCCESS ===\n\n";
    
    // Get sample businesses
    echo "1. SAMPLE BUSINESSES:\n";
    echo "=====================\n";
    $stmt = $pdo->query("SELECT name, address, area FROM businesses WHERE address IS NOT NULL AND area IS NOT NULL LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Name: " . $row['name'] . "\n";
        echo "Address: " . $row['address'] . "\n";
        echo "Area: " . $row['area'] . "\n";
        echo "---\n";
    }
    
    // Check Tabanan
    echo "\n2. BUSINESSES WITH 'TABANAN':\n";
    echo "=============================\n";
    $stmt = $pdo->query("SELECT name, address, area FROM businesses WHERE address LIKE '%Tabanan%' OR area LIKE '%Tabanan%' LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Name: " . $row['name'] . "\n";
        echo "Address: " . $row['address'] . "\n";
        echo "Area: " . $row['area'] . "\n";
        echo "---\n";
    }
    
    // Check Baturiti
    echo "\n3. BUSINESSES WITH 'BATURITI':\n";
    echo "==============================\n";
    $stmt = $pdo->query("SELECT name, address, area FROM businesses WHERE address LIKE '%Baturiti%' OR area LIKE '%Baturiti%' LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Name: " . $row['name'] . "\n";
        echo "Address: " . $row['address'] . "\n";
        echo "Area: " . $row['area'] . "\n";
        echo "---\n";
    }
    
    // Check Kec.
    echo "\n4. BUSINESSES WITH 'KEC.' IN ADDRESS:\n";
    echo "=====================================\n";
    $stmt = $pdo->query("SELECT name, address, area FROM businesses WHERE address LIKE '%Kec.%' LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Name: " . $row['name'] . "\n";
        echo "Address: " . $row['address'] . "\n";
        echo "Area: " . $row['area'] . "\n";
        echo "---\n";
    }
    
    // Count totals
    echo "\n5. COUNTS:\n";
    echo "==========\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM businesses WHERE address LIKE '%Tabanan%' OR area LIKE '%Tabanan%'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total with 'Tabanan': " . $row['count'] . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM businesses WHERE address LIKE '%Baturiti%' OR area LIKE '%Baturiti%'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total with 'Baturiti': " . $row['count'] . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM businesses WHERE address LIKE '%Kec.%'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total with 'Kec.': " . $row['count'] . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM businesses WHERE address LIKE '%Kecamatan%'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total with 'Kecamatan': " . $row['count'] . "\n";
    
    // Check unique area values
    echo "\n6. UNIQUE AREA VALUES (first 20):\n";
    echo "==================================\n";
    $stmt = $pdo->query("SELECT DISTINCT area FROM businesses WHERE area IS NOT NULL AND area != '' ORDER BY area LIMIT 20");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['area'] . "\n";
    }
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
