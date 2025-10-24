<?php

namespace App\Services;

use App\Models\CategoryMapping;
use Illuminate\Support\Facades\Log;

class CategoryValidationService
{
    /**
     * Validasi apakah bisnis sesuai dengan kategori yang diminta
     * Universal method untuk SEMUA kategori
     */
    public function validateBusinessCategory(
        array $place, 
        array $details, 
        string $requestedCategory,
        CategoryMapping $categoryMapping
    ): bool {
        $googleTypes = $details['types'] ?? [];
        $businessName = $details['name'] ?? $place['name'] ?? '';
        
        Log::debug("Validating business category", [
            'name' => $businessName,
            'requested_category' => $requestedCategory,
            'google_types' => $googleTypes
        ]);
        
        // Step 1: Cek strict match dengan Google types
        if ($this->matchesGoogleTypes($googleTypes, $categoryMapping)) {
            Log::debug("Category validation passed: Google types match", [
                'name' => $businessName,
                'matched_types' => array_intersect($googleTypes, $categoryMapping->google_types)
            ]);
            return true;
        }
        
        // Step 2: Cek keyword di nama bisnis
        if ($this->matchesKeywords($businessName, $categoryMapping)) {
            Log::debug("Category validation passed: Keywords match", [
                'name' => $businessName,
                'matched_keywords' => $this->getMatchedKeywords($businessName, $categoryMapping)
            ]);
            return true;
        }
        
        // Step 3: Jika ada multiple types, analisis keyword dominan
        if (!empty($googleTypes)) {
            $dominantCategory = $this->getDominantCategoryFromName($businessName);
            if ($dominantCategory === $requestedCategory) {
                Log::debug("Category validation passed: Dominant category from name", [
                    'name' => $businessName,
                    'dominant_category' => $dominantCategory
                ]);
                return true;
            }
        }
        
        Log::debug("Category validation failed: No match found", [
            'name' => $businessName,
            'requested_category' => $requestedCategory,
            'google_types' => $googleTypes
        ]);
        
        return false;
    }
    
    /**
     * Tentukan kategori utama dari Google types
     */
    public function getBusinessPrimaryCategory(array $googleTypes, string $businessName): string
    {
        // Map Google types ke kategori kita dengan priority
        $typeMapping = [
            'cafe' => 'Café',
            'coffee_shop' => 'Café',
            'restaurant' => 'Restoran',
            'food' => 'Restoran',
            'school' => 'Sekolah',
            'university' => 'Sekolah',
            'lodging' => 'Hotel', // Default, akan di-override jika ada keyword villa
            'hotel' => 'Hotel',
            'tourist_attraction' => 'Popular Spot',
            'point_of_interest' => 'Popular Spot',
            'park' => 'Popular Spot',
            'natural_feature' => 'Popular Spot',
            'bar' => 'Lainnya',
            'night_club' => 'Lainnya',
            'shopping_mall' => 'Lainnya',
            'gym' => 'Lainnya',
            'spa' => 'Lainnya',
            'coworking_space' => 'Lainnya'
        ];
        
        // Priority order: type yang lebih spesifik dapat priority lebih tinggi
        $priorityMapping = [
            'hotel' => 10,
            'cafe' => 9,
            'coffee_shop' => 9,
            'restaurant' => 8,
            'school' => 10,
            'university' => 10,
            'tourist_attraction' => 7,
            'lodging' => 5,  // Lower priority (generic)
            'food' => 6,     // Lower priority (generic)
            'point_of_interest' => 4,  // Lowest priority (very generic)
            'bar' => 8,
            'night_club' => 8,
            'gym' => 8,
            'spa' => 8,
            'coworking_space' => 8,
            'shopping_mall' => 8,
            'park' => 7,
            'natural_feature' => 7
        ];
        
        $matchedTypes = [];
        foreach ($googleTypes as $type) {
            if (isset($typeMapping[$type])) {
                $category = $typeMapping[$type];
                $priority = $priorityMapping[$type] ?? 1;
                
                // Jika belum ada atau priority lebih tinggi, update
                if (!isset($matchedTypes[$category]) || $matchedTypes[$category] < $priority) {
                    $matchedTypes[$category] = $priority;
                }
            }
        }
        
        // Special case: jika ada 'lodging' tapi nama mengandung 'villa', override ke Villa
        if (isset($matchedTypes['Hotel']) && $this->hasVillaKeywords($businessName)) {
            $matchedTypes['Villa'] = 9; // Higher priority than Hotel
            unset($matchedTypes['Hotel']);
        }
        
        // Return kategori dengan priority tertinggi
        if (!empty($matchedTypes)) {
            arsort($matchedTypes);
            return key($matchedTypes);
        }
        
        return 'unknown';
    }
    
    /**
     * Cek kecocokan Google types dengan kategori
     */
    public function matchesGoogleTypes(array $googleTypes, CategoryMapping $categoryMapping): bool
    {
        $intersection = array_intersect($googleTypes, $categoryMapping->google_types);
        return !empty($intersection);
    }
    
    /**
     * Cek kecocokan keyword di nama bisnis
     */
    public function matchesKeywords(string $businessName, CategoryMapping $categoryMapping): bool
    {
        $name = strtolower($businessName);
        $allKeywords = $categoryMapping->getAllKeywordsAttribute();
        
        foreach ($allKeywords as $keyword) {
            if (strpos($name, strtolower($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Analisis keyword dominan untuk multiple types
     */
    public function getDominantCategoryFromName(string $businessName): ?string
    {
        $name = strtolower($businessName);
        
        // Keyword mapping per kategori dengan strength
        $categoryKeywords = [
            'Hotel' => ['hotel', 'resort', 'penginapan', 'akomodasi', 'inn', 'hostel', 'motel'],
            'Café' => ['cafe', 'coffee', 'kopi', 'espresso', 'cappuccino', 'latte', 'barista', 'roastery'],
            'Restoran' => ['restaurant', 'restoran', 'rumah makan', 'warung', 'dining', 'kuliner'],
            'Sekolah' => ['school', 'sekolah', 'sd', 'smp', 'sma', 'universitas', 'kampus', 'academy'],
            'Villa' => ['villa', 'private villa', 'homestay', 'vacation rental'],
            'Popular Spot' => ['beach', 'pantai', 'waterfall', 'temple', 'pura', 'museum', 'gallery', 'park'],
            'Lainnya' => ['gym', 'spa', 'coworking', 'mall', 'bar', 'club', 'nightclub', 'salon']
        ];
        
        $categoryScores = [];
        
        foreach ($categoryKeywords as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($name, $keyword) !== false) {
                    $score++;
                }
            }
            if ($score > 0) {
                $categoryScores[$category] = $score;
            }
        }
        
        if (empty($categoryScores)) {
            return null;
        }
        
        // Return kategori dengan score tertinggi
        arsort($categoryScores);
        return key($categoryScores);
    }
    
    /**
     * Cek apakah nama bisnis mengandung keyword villa
     */
    private function hasVillaKeywords(string $businessName): bool
    {
        $name = strtolower($businessName);
        $villaKeywords = ['villa', 'private villa', 'homestay', 'vacation rental'];
        
        foreach ($villaKeywords as $keyword) {
            if (strpos($name, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get matched keywords untuk logging
     */
    private function getMatchedKeywords(string $businessName, CategoryMapping $categoryMapping): array
    {
        $name = strtolower($businessName);
        $allKeywords = $categoryMapping->getAllKeywordsAttribute();
        $matched = [];
        
        foreach ($allKeywords as $keyword) {
            if (strpos($name, strtolower($keyword)) !== false) {
                $matched[] = $keyword;
            }
        }
        
        return $matched;
    }
}
