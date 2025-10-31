<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ScrapeSession;
use App\Models\BaliRegion;
use App\Models\CategoryMapping;
use App\Services\CategoryValidationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ScrapingOrchestratorService
{
    private GooglePlacesService $googlePlacesService;
    private NewBusinessDetectionService $detectionService;
    private CategoryValidationService $categoryValidation;
    private ScrapeSession $currentSession;

    public function __construct(
        GooglePlacesService $googlePlacesService,
        NewBusinessDetectionService $detectionService,
        CategoryValidationService $categoryValidation
    ) {
        $this->googlePlacesService = $googlePlacesService;
        $this->detectionService = $detectionService;
        $this->categoryValidation = $categoryValidation;
    }

    /**
     * Start initial scraping for a region (supports both kabupaten name and zone name)
     */
    public function startInitialScraping(string $regionName, array $categories = []): ScrapeSession
    {
        // Check if it's a full kabupaten name (e.g., "Badung") or a zone name (e.g., "Badung - Kuta & Seminyak")
        $regions = BaliRegion::where('type', 'kabupaten')
            ->where(function($query) use ($regionName) {
                $query->where('name', $regionName)
                      ->orWhere('name', 'LIKE', "{$regionName} - %");
            })
            ->get();
        
        if ($regions->isEmpty()) {
            throw new \Exception("Region {$regionName} not found");
        }

        $this->currentSession = ScrapeSession::create([
            'session_type' => 'initial',
            'target_area' => $regionName,
            'target_categories' => $categories,
            'started_at' => now(),
            'status' => 'running',
        ]);

        Log::info("Starting initial scraping for {$regionName}", [
            'session_id' => $this->currentSession->id,
            'categories' => $categories,
            'zones_count' => $regions->count()
        ]);

        try {
            // Scrape all zones for this kabupaten
            foreach ($regions as $region) {
                $this->performInitialScraping($region, $categories);
            }
            
            $this->currentSession->markCompleted();
            
            Log::info("Initial scraping completed for {$regionName}", [
                'session_id' => $this->currentSession->id,
                'businesses_found' => $this->currentSession->businesses_found,
                'businesses_new' => $this->currentSession->businesses_new,
                'estimated_cost' => $this->currentSession->estimated_cost
            ]);
            
        } catch (\Exception $e) {
            $this->currentSession->markFailed($e->getMessage());
            Log::error("Initial scraping failed for {$regionName}: " . $e->getMessage());
            throw $e;
        }

        return $this->currentSession;
    }

    /**
     * Start NEW BUSINESS ONLY scraping
     */
    public function startNewBusinessOnlyScraping(string $regionName, array $categories = [], int $confidenceThreshold = 60): ScrapeSession
    {
        // Check if it's a full kabupaten name (e.g., "Badung") or a zone name (e.g., "Badung - Kuta & Seminyak")
        $regions = BaliRegion::where('type', 'kabupaten')
            ->where(function($query) use ($regionName) {
                $query->where('name', $regionName)
                      ->orWhere('name', 'LIKE', "{$regionName} - %");
            })
            ->get();
        
        if ($regions->isEmpty()) {
            throw new \Exception("Region {$regionName} not found");
        }

        $this->currentSession = ScrapeSession::create([
            'session_type' => 'initial',
            'target_area' => $regionName,
            'target_categories' => $categories,
            'started_at' => now(),
            'status' => 'running',
        ]);

        Log::info("Starting NEW BUSINESS ONLY scraping for {$regionName}", [
            'session_id' => $this->currentSession->id,
            'categories' => $categories,
            'confidence_threshold' => $confidenceThreshold,
            'zones_count' => $regions->count()
        ]);

        try {
            // Scrape all zones for this kabupaten - NEW BUSINESS ONLY
            foreach ($regions as $region) {
                $this->performNewBusinessOnlyScraping($region, $categories, $confidenceThreshold);
            }
            
            $this->currentSession->markCompleted();
            
            Log::info("New business only scraping completed for {$regionName}", [
                'session_id' => $this->currentSession->id,
                'businesses_found' => $this->currentSession->businesses_found,
                'businesses_new' => $this->currentSession->businesses_new,
                'estimated_cost' => $this->currentSession->estimated_cost,
                'api_calls_saved' => '90-95% compared to full scraping'
            ]);
            
        } catch (\Exception $e) {
            $this->currentSession->markFailed($e->getMessage());
            Log::error("New business only scraping failed for {$regionName}: " . $e->getMessage());
            throw $e;
        }

        return $this->currentSession;
    }

    /**
     * Start weekly update scraping
     */
    public function startWeeklyUpdate(array $regions = [], array $categories = []): ScrapeSession
    {
        $targetArea = empty($regions) ? 'All Bali' : implode(', ', $regions);
        
        $this->currentSession = ScrapeSession::create([
            'session_type' => 'weekly',
            'target_area' => $targetArea,
            'target_categories' => $categories,
            'started_at' => now(),
            'status' => 'running',
        ]);

        Log::info("Starting weekly update scraping", [
            'session_id' => $this->currentSession->id,
            'regions' => $regions,
            'categories' => $categories
        ]);

        try {
            $this->performWeeklyUpdate($regions, $categories);
            $this->currentSession->markCompleted();
            
            Log::info("Weekly update completed", [
                'session_id' => $this->currentSession->id,
                'businesses_found' => $this->currentSession->businesses_found,
                'businesses_new' => $this->currentSession->businesses_new,
                'estimated_cost' => $this->currentSession->estimated_cost
            ]);
            
        } catch (\Exception $e) {
            $this->currentSession->markFailed($e->getMessage());
            Log::error("Weekly update failed: " . $e->getMessage());
            throw $e;
        }

        return $this->currentSession;
    }

    /**
     * OPTIMIZED: Perform NEW BUSINESS ONLY scraping for a region
     * Version 2.0 with pagination, caching, batch operations, and advanced filtering
     */
    private function performNewBusinessOnlyScraping(BaliRegion $region, array $categories, int $confidenceThreshold): void
    {
        set_time_limit(1800); // 30 minutes
        
        $categories = empty($categories) ? $this->getAllCategories() : $categories;
        $candidates = [];
        $apiCallsCount = 0;
        $estimatedCost = 0;
        $startTime = time();

        Log::info("Starting scraping", [
            'region' => $region->name,
            'categories' => $categories,
            'confidence_threshold' => $confidenceThreshold
        ]);

        // OPTIMIZATION 1: Batch load existing place IDs ONCE with caching
        $existingPlaceIds = Cache::remember('existing_place_ids', 300, function() {
            return Business::whereNotNull('place_id')
                ->pluck('place_id')
                ->toArray();
        });
        
        // OPTIMIZATION 2: Get adaptive review threshold for this area
        $reviewThreshold = $this->getReviewThresholdForArea($region->name);

        // Step 1: Text Search with 1-2 optimized queries per category
        foreach ($categories as $category) {
            $categoryMapping = CategoryMapping::where('brief_category', $category)->first();

            if (!$categoryMapping) {
                Log::warning("Category mapping not found for: {$category}");
                continue;
            }
            
            $queries = $this->getOptimizedQueries($category, $region->name);
            
            foreach ($queries as $query) {
                try {
                    $cacheKey = 'text_search:' . md5($query . $region->id);
                    
                    $result = Cache::remember($cacheKey, 3600, function() use ($query, $categoryMapping, &$apiCallsCount, &$estimatedCost) {
                        $paginatedResult = $this->googlePlacesService->textSearchWithPagination($query, [
                        'type' => $categoryMapping->google_types[0] ?? null,
                            'language' => 'id'
                        ]);
                        
                        // Track actual pagination calls
                        $pagesCount = $paginatedResult['actual_api_calls'] ?? $paginatedResult['pages_fetched'] ?? 1;
                        $apiCallsCount += $pagesCount;
                        $estimatedCost += ($pagesCount * 0.032);
                        
                        return $paginatedResult;
                    });
                    
                    if (isset($result['results']) && is_array($result['results'])) {
                        foreach ($result['results'] as $place) {
                            // PRE-FILTERING: Cek apakah likely new business DAN matches category
                            if ($this->isLikelyNewBusiness($place, $existingPlaceIds, $region, $reviewThreshold) &&
                                $this->matchesCategoryBasic($place, $category, $categoryMapping)) {
                                $candidates[$place['place_id']] = [
                                    'place' => $place,
                                    'category' => $category
                                ];
                            } else if (!$this->matchesCategoryBasic($place, $category, $categoryMapping)) {
                                // Log rejection untuk debugging
                                Log::debug("Pre-filter rejected (category mismatch)", [
                                    'name' => $place['name'] ?? 'Unknown',
                                    'requested_category' => $category,
                                    'reason' => 'basic_category_check_failed'
                                ]);
                            }
                        }
                    }
                    
                    usleep(150000); // Rate limiting
                    
                } catch (\Exception $e) {
                    Log::warning("Text search failed for query: {$query} - " . $e->getMessage());
                }
            }
        }

        Log::info("Text search completed", [
            'region' => $region->name,
            'candidates_found' => count($candidates),
            'api_calls_used' => $apiCallsCount,
            'estimated_cost' => '$' . number_format($estimatedCost, 2)
        ]);

        // Step 2: Confirm with Place Details and Review Date Analysis
        $this->confirmAndSaveNewBusinesses($candidates, $confidenceThreshold, $apiCallsCount, $estimatedCost);
    }

    /**
     * Pre-filter businesses by review count (OPTIMIZATION: No Place Details needed!)
     */
    private function isLikelyNewBusiness(array $place, array $existingPlaceIds, BaliRegion $region, int $reviewThreshold): bool
    {
        $placeId = $place['place_id'] ?? '';
        
        // 1. Skip existing businesses
        if (in_array($placeId, $existingPlaceIds)) {
            return false;
        }
        
        // 2. Skip closed businesses
        $businessStatus = $place['business_status'] ?? '';
        if (in_array($businessStatus, ['CLOSED_TEMPORARILY', 'CLOSED_PERMANENTLY'])) {
            return false;
        }
        
        // 3. PRIMARY FILTER: Review count (available in Text Search response!)
        $reviewCount = $place['user_ratings_total'] ?? 0;
        $name = strtolower($place['name'] ?? '');
        
        // Low review count: definitely check
        if ($reviewCount < $reviewThreshold) {
            return true; // Likely new!
        }
        
        // 4. SELECTIVE VIRAL DETECTION (v4.1)
        // Medium review count (16-100): check ONLY if has keyword indicators
        if ($reviewCount >= $reviewThreshold && $reviewCount <= 100) {
            
            // Universal keywords (Indo + Eng)
            $newKeywords = [
                'new', 'baru', 'terbaru',
                'grand opening', 'soft opening',
                'recently opened', 'baru dibuka', 'baru buka',
                'coming soon', 'segera dibuka',
                'opening soon', 'dibuka baru-baru ini',
                '2024', '2025' // Year indicators
            ];
            
            foreach ($newKeywords as $keyword) {
                if (strpos($name, $keyword) !== false) {
                    Log::info("✨ Selective viral candidate (keyword match)", [
                        'place_id' => $placeId,
                        'name' => $place['name'],
                        'review_count' => $reviewCount,
                        'keyword' => $keyword,
                        'region' => $region->name
                    ]);
                    return true; // Has signal - worth checking!
                }
            }
            
            // No keywords found - skip to save cost
            Log::debug("Skipping medium review count (no keyword signal)", [
                'review_count' => $reviewCount,
                'name' => $place['name']
            ]);
            return false; // Save $0.017
        }
        
        // 5. HIGH review count (101+): Only if very strong keyword signal
        if ($reviewCount > 100) {
            $strongKeywords = ['new', 'baru', 'grand opening', '2025'];
            foreach ($strongKeywords as $keyword) {
                if (strpos($name, $keyword) !== false) {
                    Log::info("High review count with strong keyword", [
                        'name' => $place['name'],
                        'review_count' => $reviewCount,
                        'keyword' => $keyword
                    ]);
                    return true;
                }
            }
            return false; // Likely established
        }
        
        // 6. Geolocation validation
        if (isset($place['geometry']['location'])) {
            $placeLat = $place['geometry']['location']['lat'];
            $placeLng = $place['geometry']['location']['lng'];
            
            $distance = $this->calculateDistance(
                (float) $region->center_lat,
                (float) $region->center_lng,
                (float) $placeLat,
                (float) $placeLng
            );
            
            if ($distance > $region->search_radius + 2000) {
                return false; // Too far
            }
        }
        
        return false;
    }

    /**
     * Detect review spike pattern (viral new business indicator)
     * 
     * Universal patterns work for all regions
     */
    private function hasReviewSpike(array $reviews, int $totalReviews): array
    {
        if (empty($reviews) || count($reviews) < 5) {
            return ['has_spike' => false, 'reason' => 'insufficient_reviews'];
        }
        
        // Sort reviews by time (oldest first)
        usort($reviews, fn($a, $b) => $a['time'] - $b['time']);
        
        $oldestReview = $reviews[0]['time'];
        $daysSinceFirst = (time() - $oldestReview) / 86400;
        
        // Pattern 1: Viral new business (30+ reviews in < 90 days)
        if ($totalReviews >= 30 && $daysSinceFirst < 90) {
            return [
                'has_spike' => true,
                'pattern' => 'viral_new',
                'reviews_per_day' => round($totalReviews / $daysSinceFirst, 2),
                'days_since_first' => round($daysSinceFirst),
                'reason' => sprintf("30+ reviews dalam %.0f hari", $daysSinceFirst)
            ];
        }
        
        // Pattern 2: Popular new business (50+ reviews in < 180 days)
        if ($totalReviews >= 50 && $daysSinceFirst < 180) {
            return [
                'has_spike' => true,
                'pattern' => 'popular_new',
                'reviews_per_day' => round($totalReviews / $daysSinceFirst, 2),
                'days_since_first' => round($daysSinceFirst),
                'reason' => sprintf("50+ reviews dalam %.0f hari", $daysSinceFirst)
            ];
        }
        
        // Pattern 3: Steady high growth (>1 review/day, < 180 days)
        if ($daysSinceFirst > 0 && $daysSinceFirst < 180) {
            $reviewsPerDay = $totalReviews / $daysSinceFirst;
            if ($reviewsPerDay >= 1) {
                return [
                    'has_spike' => true,
                    'pattern' => 'steady_growth',
                    'reviews_per_day' => round($reviewsPerDay, 2),
                    'days_since_first' => round($daysSinceFirst),
                    'reason' => sprintf("Growth rate %.1f reviews/day", $reviewsPerDay)
                ];
            }
        }
        
        return ['has_spike' => false, 'reason' => 'no_spike_detected'];
    }

    /**
     * Get adaptive review threshold based on area characteristics
     */
    private function getReviewThresholdForArea(string $regionName): int
    {
        $regionName = strtolower($regionName);
        
        // Tourist/popular areas: stricter threshold
        if (strpos($regionName, 'badung') !== false || 
            strpos($regionName, 'denpasar') !== false ||
            strpos($regionName, 'gianyar') !== false) {
            return 15; // Businesses get reviews faster here
        }
        
        // Medium areas
        if (strpos($regionName, 'tabanan') !== false ||
            strpos($regionName, 'buleleng') !== false ||
            strpos($regionName, 'klungkung') !== false) {
            return 12;
        }
        
        // Remote/small areas: more lenient
        // (Jembrana, Karangasem, Bangli)
        return 20; // Businesses get reviews slower here
    }

    /**
     * Get area type for logging
     */
    private function getAreaType(string $regionName): string
    {
        $regionName = strtolower($regionName);
        
        if (strpos($regionName, 'badung') !== false || 
            strpos($regionName, 'denpasar') !== false ||
            strpos($regionName, 'gianyar') !== false) {
            return 'tourist/popular';
        }
        
        if (strpos($regionName, 'tabanan') !== false ||
            strpos($regionName, 'buleleng') !== false ||
            strpos($regionName, 'klungkung') !== false) {
            return 'medium';
        }
        
        return 'remote/small';
    }

    /**
     * Get optimized queries (1-2 per category)
     */
    private function getOptimizedQueries(string $category, string $regionName): array
    {
        // OPTIMIZED: Only 1-2 most effective queries
        return [
            "{$category} {$regionName}",              // General query (most effective)
            "new {$category} {$regionName}",          // Specific for new businesses
        ];
    }

    /**
     * Confirm candidates with Place Details and save new businesses
     */
    private function confirmAndSaveNewBusinesses(array $candidates, int $confidenceThreshold, int &$apiCallsCount, float &$estimatedCost): void
    {
        $businessesProcessed = 0;
        $businessesNew = 0;
        $businessesRejected = 0;
        
        Log::info("Starting Place Details confirmation", [
            'candidates' => count($candidates)
        ]);
        
        foreach ($candidates as $placeId => $data) {
            $place = $data['place'];
            $category = $data['category'];
            
            // Get Place Details with minimal fields
            $details = $this->googlePlacesService->placeDetails($placeId, $this->getMinimalFields());
            
            if (!$details) continue;
            
            $apiCallsCount++;
            $estimatedCost += 0.017;

            // ========== POST-FILTERING: VALIDASI KATEGORI (UNIVERSAL) ==========
            $detailsResult = $details['result'] ?? $details;

            if (!$this->categoryValidation->validateBusinessCategory(
                $place, 
                $detailsResult, 
                $category,
                CategoryMapping::where('brief_category', $category)->first()
            )) {
                $businessesRejected++;
                Log::info("Rejected: Category validation failed", [
                    'name' => $detailsResult['name'] ?? 'Unknown',
                    'place_id' => $placeId,
                    'requested_category' => $category,
                    'google_types' => $detailsResult['types'] ?? [],
                    'reason' => 'strict_category_validation_failed'
                ]);
                continue; // Skip bisnis ini
            }
            // ===================================================================

            try {
                $business = Business::firstOrNew(['place_id' => $placeId]);
                $reviews = $details['reviews'] ?? [];
                $photos = $details['photos'] ?? [];
                
                // CRITICAL: Review date analysis (final confirmation)
                if (!empty($reviews)) {
                    $oldestReview = min(array_column($reviews, 'time'));
                    $monthsOld = (time() - $oldestReview) / (30 * 24 * 3600);
                    
                    // === NEW: SPIKE DETECTION (v4.1) ===
                    $totalReviews = $details['result']['user_ratings_total'] ?? 0;
                    $spikeAnalysis = $this->hasReviewSpike($reviews, $totalReviews);
                    
                    if ($spikeAnalysis['has_spike']) {
                        // Viral business detected!
                        Log::info("🔥 VIRAL BUSINESS DETECTED", [
                            'place_id' => $placeId,
                            'name' => $details['result']['name'] ?? 'Unknown',
                            'pattern' => $spikeAnalysis['pattern'],
                            'reviews_per_day' => $spikeAnalysis['reviews_per_day'],
                            'days_since_first' => $spikeAnalysis['days_since_first'],
                            'reason' => $spikeAnalysis['reason']
                        ]);
                        
                        // PASS: Spike detected, proceed (even if > 6 months)
                        // Spike indicates legitimate viral/new business
                    } else {
                        // No spike: apply standard 6-month rule
                        if ($monthsOld > 6) {
                        $businessesRejected++;
                            Log::info("Skipping: Too old (no spike detected)", [
                                'place_id' => $placeId,
                                'name' => $details['result']['name'] ?? 'Unknown',
                                'months_old' => round($monthsOld, 1)
                        ]);
                        continue;
                    }
                }
                
                    // === NEW: PHOTO TIMESTAMP VALIDATION (v4.1) ===
                    $photos = $details['result']['photos'] ?? [];
                    
                    if (!empty($photos)) {
                        // Extract photo timestamps (if available)
                        $photoTimestamps = array_filter(array_map(function($photo) {
                            return $photo['time'] ?? null;
                        }, $photos));
                        
                        if (!empty($photoTimestamps)) {
                            $oldestPhoto = min($photoTimestamps);
                            $photoMonthsOld = (time() - $oldestPhoto) / (30 * 24 * 3600);
                            
                            // Cross-validation: photo age vs review age
                            // If photos are OLD (>12 months) but reviews are NEW (<6 months) = suspicious
                            if ($photoMonthsOld > 12 && $monthsOld < 6) {
                                Log::warning("⚠️ Photo-review age mismatch", [
                                    'place_id' => $placeId,
                                    'name' => $details['result']['name'] ?? 'Unknown',
                                    'photo_age_months' => round($photoMonthsOld, 1),
                                    'review_age_months' => round($monthsOld, 1),
                                    'assessment' => 'Likely old business with recent review surge'
                                ]);
                                
                                // Skip UNLESS has spike (spike = legitimate viral event)
                                if (!$spikeAnalysis['has_spike']) {
                                    $businessesRejected++;
                                    Log::info("Skipping: Photo-review mismatch without spike", [
                                        'place_id' => $placeId
                                    ]);
                                    continue; // False positive - skip
                                } else {
                                    Log::info("✅ Keeping: Has spike despite mismatch", [
                                        'place_id' => $placeId,
                                        'spike_pattern' => $spikeAnalysis['pattern'],
                                        'note' => 'Viral old business - still valuable'
                                    ]);
                                    // Keep it - viral old business still valuable data
                                }
                            }
                        }
                    }
                }
                
                // Calculate full confidence score
                $fullAnalysis = $this->detectionService->calculateNewBusinessScore(
                    $business,
                    $details,
                    $reviews,
                    $photos
                );
                
                $advancedConfidence = $fullAnalysis['score'];
                $confidenceLevel = $fullAnalysis['confidence'];
                
                // Apply confidence threshold
                if ($advancedConfidence >= $confidenceThreshold) {
                    $this->processBusinessData($details['result'] ?? $details, $place, $business, $category);
                    $businessesNew++;
                    
                    Log::info("New business confirmed", [
                        'name' => $details['name'] ?? 'Unknown',
                        'confidence' => $advancedConfidence,
                        'review_count' => $details['user_ratings_total'] ?? 0,
                        'months_old' => isset($monthsOld) ? round($monthsOld, 1) : 'N/A'
                    ]);
                } else {
                    $businessesRejected++;
                    Log::debug("Rejected: Low confidence", [
                        'name' => $details['name'] ?? 'Unknown',
                        'confidence' => $advancedConfidence,
                        'threshold' => $confidenceThreshold
                    ]);
                }
                
                $businessesProcessed++;
                
            } catch (\Exception $e) {
                Log::error("Error processing business: " . $e->getMessage());
            }
        }

        // Update session stats
        $this->currentSession->update([
            'businesses_found' => $businessesProcessed,
            'businesses_new' => $businessesNew,
            'api_calls_count' => $apiCallsCount,
            'estimated_cost' => $estimatedCost,
        ]);
        
        Log::info("Scraping completed", [
            'processed' => $businessesProcessed,
            'new' => $businessesNew,
            'rejected' => $businessesRejected,
            'success_rate' => $businessesProcessed > 0 ? round(($businessesNew / $businessesProcessed) * 100, 1) . '%' : '0%',
            'api_calls_total' => $apiCallsCount,
            'cost_total' => '$' . number_format($estimatedCost, 2)
        ]);

        // ========== COMPREHENSIVE LOGGING: CATEGORY FILTERING SUMMARY ==========
        Log::info("Category filtering summary", [
            'requested_categories' => $this->currentSession->target_categories,
            'total_candidates' => count($candidates),
            'passed_pre_filter' => $businessesProcessed + $businessesRejected,
            'passed_post_filter' => $businessesNew,
            'rejected_count' => $businessesRejected,
            'rejection_rate' => $businessesProcessed > 0 ? round(($businessesRejected / $businessesProcessed) * 100, 1) . '%' : '0%',
            'category_accuracy' => $businessesProcessed > 0 ? round(($businessesNew / $businessesProcessed) * 100, 1) . '%' : '0%'
        ]);
        // =====================================================================
    }

    /**
     * Get minimal fields for cost efficiency
     */
    private function getMinimalFields(): array
    {
        return [
            'place_id',
            'name',
            'formatted_address',
            'geometry',
            'reviews',
            'photos',
            'user_ratings_total',
            'rating',
            'business_status',
            'types'
        ];
    }

    /**
     * Perform Nearby Search (Primary Strategy)
     */
    private function performNearbySearch(
        BaliRegion $region, 
        CategoryMapping $categoryMapping, 
        array $existingPlaceIds,
        int &$apiCallsCount,
        float &$estimatedCost
    ): array {
        $places = [];
        
        // Use first Google type for nearby search
        $googleType = $categoryMapping->google_types[0] ?? null;
        
        if (!$googleType) {
            return $places;
        }
        
        Log::info("Performing Nearby Search", [
            'region' => $region->name,
            'type' => $googleType,
            'radius' => $region->search_radius
        ]);
        
        try {
            $result = $this->googlePlacesService->nearbySearchWithPagination(
                (float) $region->center_lat,
                (float) $region->center_lng,
                (int) $region->search_radius,
                ['type' => $googleType]
            );
            
            $apiCallsCount += $result['actual_api_calls'] ?? 1;
            $estimatedCost += ($result['actual_api_calls'] ?? 1) * 0.032;
            
            if (isset($result['results']) && is_array($result['results'])) {
                foreach ($result['results'] as $place) {
                    // Pre-filter with business_status
                    if ($this->isNewBusiness($place, $categoryMapping->brief_category, $existingPlaceIds, $region)) {
                        $places[$place['place_id']] = [
                            'place' => $place,
                            'category' => $categoryMapping->brief_category,
                            'source' => 'nearby_search'
                        ];
                    }
                }
            }
            
            Log::info("Nearby Search completed", [
                'region' => $region->name,
                'type' => $googleType,
                'results_found' => count($result['results'] ?? []),
                'candidates_after_prefilter' => count($places)
            ]);
                        
                    } catch (\Exception $e) {
            Log::warning("Nearby Search failed: " . $e->getMessage());
        }
        
        return $places;
    }

    /**
     * Perform Text Search (Fallback Strategy)
     */
    private function performTextSearch(
        BaliRegion $region,
        string $category,
        CategoryMapping $categoryMapping,
        array $existingPlaceIds,
        int &$apiCallsCount,
        float &$estimatedCost
    ): array {
        $places = [];
        
        // REDUCED: Only 2 most effective queries (not 5)
        $queries = $this->getOptimizedQueries($category, $region->name);
        
        foreach ($queries as $query) {
            try {
                $cacheKey = 'text_search:' . md5($query . $region->id);
                
                $result = Cache::remember($cacheKey, 3600, function() use ($query, $categoryMapping, &$apiCallsCount, &$estimatedCost) {
                    $paginatedResult = $this->googlePlacesService->textSearchWithPagination($query, [
                        'type' => $categoryMapping->google_types[0] ?? null,
                        'language' => 'id'
                    ]);
                    
                    // Track actual pagination calls
                    $pagesCount = $paginatedResult['actual_api_calls'] ?? $paginatedResult['pages_fetched'] ?? 1;
                    $apiCallsCount += $pagesCount;
                    $estimatedCost += ($pagesCount * 0.032);
                    
                    return $paginatedResult;
                });
                
                if (isset($result['results']) && is_array($result['results'])) {
                    foreach ($result['results'] as $place) {
                        if ($this->isNewBusiness($place, $category, $existingPlaceIds, $region)) {
                            $places[$place['place_id']] = [
                                'place' => $place,
                                'category' => $category,
                                'source' => 'text_search'
                            ];
                        }
                    }
                }
                
                usleep(150000);
                        
                    } catch (\Exception $e) {
                Log::warning("Text search failed for query: {$query} - " . $e->getMessage());
            }
        }
        
        return $places;
    }

    /**
     * Get optimized queries (reduced from 5 to 2)
     */

    /**
     * Process new business candidates with adaptive threshold
     */
    private function processNewBusinessCandidates(
        array $allPlaces,
        int $baseConfidenceThreshold,
        int &$apiCallsCount,
        float &$estimatedCost
    ): void {
        $businessesProcessed = 0;
        $businessesNew = 0;
        $businessesRejected = 0;
        
        foreach ($allPlaces as $placeId => $data) {
            $place = $data['place'];
            $category = $data['category'];
            $source = $data['source'] ?? 'unknown';
            
            // Get Place Details
            $details = $this->googlePlacesService->placeDetails($placeId);
            
            if (!$details) continue;
            
                    $apiCallsCount++;
                    $estimatedCost += 0.017;

            try {
                $business = Business::firstOrNew(['place_id' => $placeId]);
                $reviews = $details['reviews'] ?? [];
                $photos = $details['photos'] ?? [];
                
                // Calculate confidence with full analysis
                $fullAnalysis = $this->detectionService->calculateNewBusinessScore(
                    $business,
                    $details,
                    $reviews,
                    $photos
                );
                
                $advancedConfidence = $fullAnalysis['score'];
                $confidenceLevel = $fullAnalysis['confidence'];
                $businessStatus = $details['business_status'] ?? '';
                
                // ADAPTIVE: Get threshold for this region
                $region = BaliRegion::find($place['region_id'] ?? null);
                $adaptiveThreshold = $this->getAdaptiveConfidenceThreshold($region, $baseConfidenceThreshold);
                
                // Multi-level confidence filtering with OPENED_RECENTLY bonus
                $shouldProcess = false;
                $reason = '';
                
                // PRIORITY 1: business_status = OPENED_RECENTLY (strongest signal)
                if ($businessStatus === 'OPENED_RECENTLY') {
                    $shouldProcess = true;
                    $reason = "business_status=OPENED_RECENTLY (golden signal)";
                }
                // PRIORITY 2: High confidence above adaptive threshold
                elseif ($confidenceLevel === 'high' && $advancedConfidence >= $adaptiveThreshold) {
                    $shouldProcess = true;
                    $reason = "high confidence ({$advancedConfidence}% >= {$adaptiveThreshold}%)";
                }
                // PRIORITY 3: Medium confidence with lower threshold
                elseif ($confidenceLevel === 'medium' && $advancedConfidence >= ($adaptiveThreshold - 10)) {
                    $shouldProcess = true;
                    $reason = "medium confidence ({$advancedConfidence}% >= " . ($adaptiveThreshold - 10) . "%)";
                }
                
                if ($shouldProcess) {
                    $this->processBusinessData($details, $place, $business);
                    $businessesNew++;
                    
                    Log::info("New business accepted", [
                        'name' => $details['name'] ?? 'Unknown',
                        'confidence' => $advancedConfidence,
                        'threshold' => $adaptiveThreshold,
                        'reason' => $reason,
                        'source' => $source
                    ]);
                } else {
                    $businessesRejected++;
                    
                    Log::debug("Business rejected", [
                        'name' => $details['name'] ?? 'Unknown',
                        'confidence' => $advancedConfidence,
                        'threshold' => $adaptiveThreshold,
                        'confidence_level' => $confidenceLevel
                    ]);
                }
                
                $businessesProcessed++;
                    
                } catch (\Exception $e) {
                Log::error("Error processing business: " . $e->getMessage());
            }
        }

        // Update session stats
        $this->currentSession->update([
            'businesses_found' => $businessesProcessed,
            'businesses_new' => $businessesNew,
            'api_calls_count' => $apiCallsCount,
            'estimated_cost' => $estimatedCost,
        ]);
        
        Log::info("Business processing completed", [
            'processed' => $businessesProcessed,
            'accepted' => $businessesNew,
            'rejected' => $businessesRejected,
            'acceptance_rate' => $businessesProcessed > 0 ? round(($businessesNew / $businessesProcessed) * 100, 1) . '%' : '0%'
        ]);
    }

    /**
     * Get adaptive confidence threshold based on area size
     */
    private function getAdaptiveConfidenceThreshold(BaliRegion $region, int $baseThreshold): int
    {
        // Adjust threshold based on area characteristics
        $areaSize = $region->search_radius;
        $regionName = strtolower($region->name);
        
        // Large populated areas: higher threshold (more data available)
        if (strpos($regionName, 'badung') !== false || 
            strpos($regionName, 'denpasar') !== false) {
            return max(65, $baseThreshold);
        }
        
        // Medium areas: moderate threshold
        if (strpos($regionName, 'gianyar') !== false || 
            strpos($regionName, 'buleleng') !== false ||
            strpos($regionName, 'tabanan') !== false) {
            return max(55, min($baseThreshold, 65));
        }
        
        // Small areas: lower threshold (less data, need to be more lenient)
        return max(50, min($baseThreshold, 60));
    }

    /**
     * Perform initial scraping for a region - uses same optimized logic as performNewBusinessOnlyScraping
     */
    private function performInitialScraping(BaliRegion $region, array $categories): void
    {
        // Use the same optimized logic with default threshold of 10 (more inclusive)
        $this->performNewBusinessOnlyScraping($region, $categories, 10);
    }

    /**
     * Perform weekly update scraping
     */
    private function performWeeklyUpdate(array $regions, array $categories): void
    {
        $regions = empty($regions) ? $this->getPriorityRegions() : $regions;
        $categories = empty($categories) ? $this->getAllCategories() : $categories;

        foreach ($regions as $region) {
            Log::info("Starting weekly update for region: {$region->name}");
            
            // Use new business only scraping for weekly updates
            $this->performNewBusinessOnlyScraping($region, $categories, 60);
        }
    }

    /**
     * Process business data and save to database
     */
    private function processBusinessData(array $details, array $place = [], ?Business $existingBusiness = null, ?string $category = null): array
    {
        if (empty($details)) {
            return [];
        }

        $business = $existingBusiness ?? new Business();
        
        // Extract basic information
        $business->name = $details['name'] ?? '';
        $business->place_id = $details['place_id'] ?? '';
        $business->address = $details['formatted_address'] ?? '';
        $business->rating = $details['rating'] ?? null;
        $business->review_count = $details['user_ratings_total'] ?? 0;
        
        // Extract location data
        if (isset($details['geometry']['location'])) {
        $business->lat = $details['geometry']['location']['lat'];
        $business->lng = $details['geometry']['location']['lng'];
        }
        
        // Extract area from address
        $business->area = $this->extractAreaFromAddress($business->address);
        
        // Extract types
        // $business->types = json_encode($details['types'] ?? []); // Column doesn't exist
        
        // Assign category from scraping context
        if ($category) {
            $business->category = $category;
        } elseif (isset($place['category'])) {
            $business->category = $place['category'];
        } else {
            // Fallback: determine category from Google types
            $business->category = $this->determineCategoryFromTypes($details['types'] ?? []);
        }
        
        // Generate Google Maps URL
        $business->google_maps_url = $this->generateGoogleMapsUrl($details);
        
        // Generate and save indicators
        $reviews = $details['reviews'] ?? [];
        $photos = $details['photos'] ?? [];
        $business->indicators = $this->detectionService->generateBusinessIndicators($business, $details, $reviews, $photos);
        
        // Set timestamps
        if (!$business->exists) {
            $business->first_seen = now();
        }
        $business->last_fetched = now();
        
        // Save the business
        $business->save();

        return $business->toArray();
    }

    /**
     * Basic pre-filtering berdasarkan nama bisnis (berlaku untuk SEMUA kategori)
     * Filtering awal sebelum Place Details untuk save API cost
     */
    private function matchesCategoryBasic(array $place, string $category, CategoryMapping $categoryMapping): bool
    {
        $name = strtolower($place['name'] ?? '');
        
        // Ambil semua keywords dari category mapping
        $allKeywords = $categoryMapping->getAllKeywordsAttribute();
        
        // Jika nama mengandung salah satu keyword kategori, accept
        foreach ($allKeywords as $keyword) {
            if (strpos($name, strtolower($keyword)) !== false) {
                return true; // Match keyword kategori yang diminta
            }
        }
        
        // Jika tidak ada keyword match, cek apakah ada keyword kategori lain yang LEBIH kuat
        // Ini untuk reject bisnis yang jelas-jelas kategori lain
        $competingCategories = [
            'Café' => ['cafe', 'coffee', 'kopi'],
            'Restoran' => ['restaurant', 'restoran', 'warung'],
            'Sekolah' => ['school', 'sekolah', 'universitas'],
            'Villa' => ['villa'],
            'Hotel' => ['hotel', 'resort'],
            'Popular Spot' => ['beach', 'pantai', 'temple', 'pura', 'waterfall'],
            'Lainnya' => ['gym', 'spa', 'coworking', 'mall', 'bar', 'club']
        ];
        
        // Jika nama mengandung keyword kategori lain yang kuat, reject
        foreach ($competingCategories as $otherCategory => $keywords) {
            if ($otherCategory === $category) continue; // Skip kategori yang diminta
            
            foreach ($keywords as $keyword) {
                if (strpos($name, $keyword) !== false) {
                    // Nama mengandung keyword kategori lain
                    Log::debug("Pre-filter: Competing category detected", [
                        'name' => $place['name'],
                        'requested' => $category,
                        'detected' => $otherCategory,
                        'keyword' => $keyword
                    ]);
                    return false; // Reject karena jelas kategori lain
                }
            }
        }
        
        // Jika tidak ada keyword match tapi juga tidak ada competing keyword
        // Accept untuk divalidasi lebih lanjut di post-filtering
        return true;
    }

    /**
     * Determine category from Google Place types with priority logic
     */
    private function determineCategoryFromTypes(array $types): string
    {
        // Map Google types ke kategori kita
        $typeMapping = [
            'cafe' => 'Café',
            'coffee_shop' => 'Café',
            'restaurant' => 'Restoran',
            'food' => 'Restoran',
            'school' => 'Sekolah',
            'university' => 'Sekolah',
            'lodging' => 'Hotel', // Default ke Hotel, akan di-override jika ada 'villa' keyword
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
        foreach ($types as $type) {
            if (isset($typeMapping[$type])) {
                $category = $typeMapping[$type];
                $priority = $priorityMapping[$type] ?? 1;
                
                // Jika belum ada atau priority lebih tinggi, update
                if (!isset($matchedTypes[$category]) || $matchedTypes[$category] < $priority) {
                    $matchedTypes[$category] = $priority;
                }
            }
        }
        
        // Return kategori dengan priority tertinggi
        if (!empty($matchedTypes)) {
            arsort($matchedTypes);
            return key($matchedTypes);
        }
        
        return 'unknown';
    }

    /**
     * Generate grid points for comprehensive coverage
     */
    private function generateGridPoints(BaliRegion $region): array
    {
        $points = [];
        $centerLat = (float) $region->center_lat;
        $centerLng = (float) $region->center_lng;
        $radius = (int) $region->search_radius;
        
        // Calculate optimal grid size based on radius
        $gridSize = $this->calculateOptimalGridSize($region);
        
        // Generate grid points
        $latStep = $gridSize / 111000; // Approximate meters to degrees
        $lngStep = $gridSize / (111000 * cos(deg2rad($centerLat)));
        
        $latStart = $centerLat - ($radius / 111000);
        $latEnd = $centerLat + ($radius / 111000);
        $lngStart = $centerLng - ($radius / (111000 * cos(deg2rad($centerLat))));
        $lngEnd = $centerLng + ($radius / (111000 * cos(deg2rad($centerLat))));
        
        for ($lat = $latStart; $lat <= $latEnd; $lat += $latStep) {
            for ($lng = $lngStart; $lng <= $lngEnd; $lng += $lngStep) {
                $points[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'radius' => $gridSize
                ];
            }
        }
        
        return $points;
    }

    /**
     * Subdivide dense areas for better coverage
     */
    private function subdivideDenseArea(float $lat, float $lng, int $radius, ?string $type): array
    {
        $subdividedResults = [];
        $subRadius = $radius / 2;
        
        // Create 4 sub-areas
        $subAreas = [
            ['lat' => $lat - $subRadius/111000, 'lng' => $lng - $subRadius/(111000 * cos(deg2rad($lat))), 'radius' => $subRadius],
            ['lat' => $lat + $subRadius/111000, 'lng' => $lng - $subRadius/(111000 * cos(deg2rad($lat))), 'radius' => $subRadius],
            ['lat' => $lat - $subRadius/111000, 'lng' => $lng + $subRadius/(111000 * cos(deg2rad($lat))), 'radius' => $subRadius],
            ['lat' => $lat + $subRadius/111000, 'lng' => $lng + $subRadius/(111000 * cos(deg2rad($lat))), 'radius' => $subRadius],
        ];
        
        foreach ($subAreas as $subArea) {
            try {
                $result = $this->googlePlacesService->nearbySearch(
                    $subArea['lat'],
                    $subArea['lng'],
                    $subArea['radius'],
                    $type ? ['type' => $type] : []
                );
                
                if (isset($result['results'])) {
                        $subdividedResults = array_merge($subdividedResults, $result['results']);
                }
                
                usleep(100000); // Rate limiting
                
            } catch (\Exception $e) {
                Log::warning("Subdivision search failed: " . $e->getMessage());
            }
        }
        
        return $subdividedResults;
    }

    /**
     * Calculate optimal grid size based on region characteristics
     */
    private function calculateOptimalGridSize(BaliRegion $region): int
    {
        // Adaptive grid size based on search radius and priority
        $baseRadius = (int) $region->search_radius;
        
        // Smaller grid for smaller regions
        if ($baseRadius <= 5000) {
            return 2000; // 2km grid
        } elseif ($baseRadius <= 10000) {
            return 3000; // 3km grid
        } else {
            return 5000; // 5km grid
        }
    }

    /**
     * Calculate region bounding box
     */
    private function calculateRegionBoundingBox(BaliRegion $region): array
    {
        $centerLat = (float) $region->center_lat;
        $centerLng = (float) $region->center_lng;
        $radius = (int) $region->search_radius;
        
        return [
            'north' => $centerLat + ($radius / 111000),
            'south' => $centerLat - ($radius / 111000),
            'east' => $centerLng + ($radius / (111000 * cos(deg2rad($centerLat)))),
            'west' => $centerLng - ($radius / (111000 * cos(deg2rad($centerLat))))
        ];
    }

    /**
     * Calculate distance between two points
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Ensure all values are floats
        $lat1 = (float) $lat1;
        $lng1 = (float) $lng1;
        $lat2 = (float) $lat2;
        $lng2 = (float) $lng2;
        
        $earthRadius = 6371000; // Earth's radius in meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    /**
     * Get hot zones for priority scraping
     */
    private function getHotZones(BaliRegion $region): array
    {
        // For now, return some sample hot zones
        // In the future, this could be based on business density or other factors
        return [
            ['lat' => (float) $region->center_lat, 'lng' => (float) $region->center_lng, 'radius' => 2000],
        ];
    }

    /**
     * Get priority regions for scraping
     */
    private function getPriorityRegions(): array
    {
        return BaliRegion::where('type', 'kabupaten')
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Get all available categories
     */
    private function getAllCategories(): array
    {
        return CategoryMapping::pluck('brief_category')->toArray();
    }

    /**
     * Extract area from address string
     */
    private function extractAreaFromAddress(string $address): ?string
    {
        $parts = array_map('trim', explode(',', $address));

        // Normalization targets
        $areas = [
            'Denpasar' => 'Kota Denpasar',
            'Badung' => 'Kabupaten Badung',
            'Gianyar' => 'Kabupaten Gianyar',
            'Tabanan' => 'Kabupaten Tabanan',
            'Klungkung' => 'Kabupaten Klungkung',
            'Bangli' => 'Kabupaten Bangli',
            'Karangasem' => 'Kabupaten Karangasem',
            'Buleleng' => 'Kabupaten Buleleng',
            'Jembrana' => 'Kabupaten Jembrana',
        ];

        // Regex patterns to tolerate variants: Regency/City, lower/upper, prefixes
        $patterns = [
            'kabupaten' => '/^(kabupaten\s+)?%s(\s+regency)?$/i',
            'kota' => '/^(kota\s+)?%s(\s+city)?$/i',
            'loose' => '/.*%s.*/i'
        ];

        foreach ($parts as $part) {
            $p = trim(preg_replace('/\s+\d+/', '', $part));
            foreach ($areas as $key => $standard) {
                $escaped = preg_quote($key, '/');
                if (preg_match(sprintf($patterns['kabupaten'], $escaped), $p)
                    || preg_match(sprintf($patterns['kota'], $escaped), $p)
                    || preg_match(sprintf($patterns['loose'], $escaped), $p)) {
                    return $standard;
                }
            }
        }

        // Fallback: if address contains "Bali", return 'Bali'
        if (stripos($address, 'bali') !== false) {
            return 'Bali';
        }

        return null;
    }

    /**
     * Generate Google Maps URL
     */
    private function generateGoogleMapsUrl(array $details): ?string
    {
        $name = $details['name'] ?? '';
        $placeId = $details['place_id'] ?? '';
        
        if (empty($name) || empty($placeId)) {
        return null;
    }

        $encodedName = urlencode($name);
        return "https://www.google.com/maps/place/?q=place_id:{$placeId}";
    }


    /**
     * Check if a place is likely a new business
     */
    private function isNewBusiness(array $place, string $category, array $existingPlaceIds = [], ?BaliRegion $region = null): bool
    {
        $placeId = $place['place_id'];
        
        // 1. Check existing place IDs (NO database query)
        if (in_array($placeId, $existingPlaceIds)) {
            return false;
        }
        
        // 2. PRE-FILTER: Check business_status FIRST (before Place Details call!)
        $businessStatus = $place['business_status'] ?? '';
        
        // Strong signal: OPENED_RECENTLY
        if ($businessStatus === 'OPENED_RECENTLY') {
            Log::debug("Strong new business signal", [
                'place_id' => $placeId,
                'name' => $place['name'] ?? 'Unknown',
                'status' => 'OPENED_RECENTLY'
            ]);
            return true; // Prioritize checking details for this
        }
        
        // Skip if closed
        if (in_array($businessStatus, ['CLOSED_TEMPORARILY', 'CLOSED_PERMANENTLY'])) {
            return false;
        }
        
        // 3. Geolocation validation
        if ($region && isset($place['geometry']['location'])) {
            $placeLat = $place['geometry']['location']['lat'];
            $placeLng = $place['geometry']['location']['lng'];
            
            $distance = $this->calculateDistance(
                (float) $region->center_lat,
                (float) $region->center_lng,
                (float) $placeLat,
                (float) $placeLng
            );
            
            if ($distance > $region->search_radius + 2000) {
                return false;
            }
        }
        
        // 4. Check for new business keywords in name/types
        $keywords = ['new', 'baru', 'grand opening', 'recently opened', 'dibuka'];
        $name = strtolower($place['name'] ?? '');
        
        foreach ($keywords as $keyword) {
            if (strpos($name, $keyword) !== false) {
            return true;
        }
        }
        
        // 5. Default: check if has low review count (early stage indicator)
        $reviewCount = $place['user_ratings_total'] ?? 0;
        if ($reviewCount > 0 && $reviewCount < 10) {
            return true; // Worth checking details
        }

        return false;
    }

    /**
     * Calculate confidence score for new business
     */
    private function calculateNewBusinessConfidence(array $details): int
    {
        $confidence = 0;
        
        // Check business name for new business indicators
        $name = strtolower($details['name'] ?? '');
        $newBusinessKeywords = ['baru', 'new', 'terbaru', 'grand opening', 'buka', 'opening'];
        
        foreach ($newBusinessKeywords as $keyword) {
            if (str_contains($name, $keyword)) {
                $confidence += 30;
            }
        }

        // Check review count (new businesses typically have few reviews)
        $reviewCount = $details['user_ratings_total'] ?? 0;
        if ($reviewCount === 0) {
            $confidence += 40; // No reviews = very new
        } elseif ($reviewCount < 5) {
            $confidence += 25; // Few reviews = new
        } elseif ($reviewCount < 10) {
            $confidence += 15; // Some reviews = relatively new
        }

        // Check business status
        $businessStatus = $details['business_status'] ?? '';
        if ($businessStatus === 'OPERATIONAL') {
            $confidence += 20; // Operational is good
        }

        return min($confidence, 100); // Cap at 100%
    }

    /**
     * Process new business data
     */
    private function processNewBusinessData(array $details, array $place = []): array
    {
        if (empty($details)) {
            return [];
        }

        $business = new Business();
        $business->name = $details['name'] ?? '';
        $business->place_id = $details['place_id'] ?? '';
        $business->address = $details['formatted_address'] ?? '';
        $business->rating = $details['rating'] ?? null;
        $business->review_count = $details['user_ratings_total'] ?? 0;
        
        if (isset($details['geometry']['location'])) {
        $business->lat = $details['geometry']['location']['lat'];
        $business->lng = $details['geometry']['location']['lng'];
        }
        
        $business->area = $this->extractAreaFromAddress($business->address);
        // $business->types = json_encode($details['types'] ?? []); // Column doesn't exist
        $business->google_maps_url = $this->generateGoogleMapsUrl($details);
        $business->first_seen = now();
        $business->last_fetched = now();
        
        $business->save();

        return $business->toArray();
    }
    
    /**
     * Check for viral indicators in business name
     */
    private function checkViralIndicators(array $place, int $reviewCount, int $reviewThreshold): bool
    {
        $name = strtolower($place['name'] ?? '');
        
        // Medium review count (16-100): check ONLY if has keyword indicators
        if ($reviewCount >= $reviewThreshold && $reviewCount <= 100) {
            $newKeywords = [
                'new', 'baru', 'terbaru',
                'grand opening', 'soft opening',
                'recently opened', 'baru dibuka', 'baru buka',
                'coming soon', 'segera dibuka',
                'opening soon', 'dibuka baru-baru ini',
                '2024', '2025'
            ];
            
            foreach ($newKeywords as $keyword) {
                if (strpos($name, $keyword) !== false) {
                    Log::debug("Viral keyword detected", [
                        'name' => $place['name'] ?? 'Unknown',
                        'keyword' => $keyword,
                        'review_count' => $reviewCount
                    ]);
                    return true;
                }
            }
        }
        
        // High review count (>100): skip unless very specific indicators
        if ($reviewCount > 100) {
            $specificNewKeywords = ['2024', '2025', 'grand opening', 'soft opening'];
            foreach ($specificNewKeywords as $keyword) {
                if (strpos($name, $keyword) !== false) {
                    Log::debug("Specific new keyword detected", [
                        'name' => $place['name'] ?? 'Unknown',
                        'keyword' => $keyword,
                        'review_count' => $reviewCount
                    ]);
                    return true;
                }
            }
        }
        
        return false;
    }
}
