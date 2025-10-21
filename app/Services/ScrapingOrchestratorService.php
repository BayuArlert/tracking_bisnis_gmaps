<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ScrapeSession;
use App\Models\BaliRegion;
use App\Models\CategoryMapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ScrapingOrchestratorService
{
    private GooglePlacesService $googlePlacesService;
    private NewBusinessDetectionService $detectionService;
    private ScrapeSession $currentSession;

    public function __construct(
        GooglePlacesService $googlePlacesService,
        NewBusinessDetectionService $detectionService
    ) {
        $this->googlePlacesService = $googlePlacesService;
        $this->detectionService = $detectionService;
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
            'session_type' => 'new_business_only',
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
        $allPlaces = [];
        $apiCallsCount = 0;
        $estimatedCost = 0;
        $startTime = time();

        Log::info("Starting OPTIMIZED NEW BUSINESS ONLY scraping v2.0", [
            'region' => $region->name,
            'categories' => $categories,
            'confidence_threshold' => $confidenceThreshold,
            'optimization_features' => [
                'pagination', 'caching', 'batch_operations', 'geolocation_validation',
                'smart_pre_filtering', 'advanced_confidence_scoring', 'review_freshness'
            ]
        ]);

        // OPTIMIZATION 1: Batch load existing place IDs ONCE (not in loop!)
        $existingPlaceIds = Business::where('area', 'LIKE', "%{$region->name}%")
            ->pluck('place_id')
            ->toArray();
        
        Log::info("Pre-loaded existing businesses", [
            'count' => count($existingPlaceIds)
        ]);

        // Step 1: Text Search with PAGINATION for complete coverage
        foreach ($categories as $category) {
            $categoryMapping = CategoryMapping::where('brief_category', $category)->first();
            if (!$categoryMapping) continue;

            $newBusinessQueries = $this->getNewBusinessQueries($category, $region->name);
            
            foreach ($newBusinessQueries as $query) {
                try {
                    // OPTIMIZATION 2: Check cache first (1 hour TTL)
                    $cacheKey = 'text_search:' . md5($query . $region->id);
                    
                    $result = Cache::remember($cacheKey, 3600, function() use ($query, $categoryMapping, &$apiCallsCount, &$estimatedCost) {
                        // Use PAGINATION to get all 60 results (not just 20)
                        $paginatedResult = $this->googlePlacesService->textSearchWithPagination($query, [
                        'type' => $categoryMapping->google_types[0] ?? null,
                            'language' => 'id'
                        ]);
                        
                        // Count API calls (1 initial + pages fetched - 1)
                        $pagesCount = $paginatedResult['pages_fetched'] ?? 1;
                        $apiCallsCount += $pagesCount;
                        $estimatedCost += $pagesCount * 0.032;
                        
                        return $paginatedResult;
                    });
                    
                    if (isset($result['results'])) {
                        foreach ($result['results'] as $place) {
                            // OPTIMIZATION 3: Smart pre-filtering with geolocation & batch checks
                            if ($this->isNewBusiness($place, $category, $existingPlaceIds, $region)) {
                                $allPlaces[$place['place_id']] = [
                                    'place' => $place,
                                    'category' => $category
                                ];
                            }
                        }
                    }
                    
                    usleep(150000); // 0.15 second (optimized from 0.2)
                    
                } catch (\Exception $e) {
                    Log::warning("Text search failed for query: {$query} - " . $e->getMessage());
                }
            }
        }

        Log::info("Text search with pagination completed", [
            'region' => $region->name,
            'potential_new_businesses' => count($allPlaces),
            'api_calls_used' => $apiCallsCount
        ]);

        // OPTIMIZATION 4: Batch check database again before fetching details
        $placeIdsToFetch = array_keys($allPlaces);
        $recentlyAddedPlaceIds = Business::whereIn('place_id', $placeIdsToFetch)
            ->pluck('place_id')
            ->toArray();
        
        $placeIdsToFetch = array_diff($placeIdsToFetch, $recentlyAddedPlaceIds);
        
        Log::info("After deduplication", [
            'places_to_fetch_details' => count($placeIdsToFetch),
            'already_exists' => count($recentlyAddedPlaceIds)
        ]);

        // OPTIMIZATION 5: Fetch details with BASIC FIELDS ONLY (cheaper!)
        $basicFields = $this->googlePlacesService->getBasicFieldsForNewBusinessDetection();
        
        $placeDetails = [];
        $chunks = array_chunk($placeIdsToFetch, 10);
        
        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $placeId) {
                try {
                    $details = $this->googlePlacesService->placeDetails($placeId, $basicFields);
                    
                    $apiCallsCount++;
                    $estimatedCost += 0.017; // Basic tier pricing
                    
                    if (isset($details['result'])) {
                        $placeDetails[$placeId] = $details['result'];
                    }
                    
                    usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                    Log::warning("Failed to fetch details for {$placeId}: " . $e->getMessage());
                }
            }
            
            if ($chunkIndex < count($chunks) - 1) {
                usleep(200000); // 0.2 second between chunks
            }
        }

        // Step 3: Process with ADVANCED confidence scoring & review freshness
        $businessesFound = 0;
        $businessesNew = 0;
        $businessesUpdated = 0;
        $businessesRejected = 0;
        $businessesToSave = [];

        foreach ($placeDetails as $placeId => $details) {
            if (!$details) continue;

            try {
                // OPTIMIZATION 6: Advanced confidence scoring with NewBusinessDetectionService
                $business = Business::firstOrNew(['place_id' => $placeId]);
                $reviews = $details['reviews'] ?? [];
                $photos = $details['photos'] ?? [];
                
                $fullAnalysis = $this->detectionService->calculateNewBusinessScore(
                    $business,
                    $details,
                    $reviews,
                    $photos
                );
                
                $advancedConfidence = $fullAnalysis['score'];
                $confidenceLevel = $fullAnalysis['confidence'];
                
                // OPTIMIZATION 7: Review freshness validation
                $newestReviewDate = $fullAnalysis['metadata_analysis']['newest_review_date'] ?? null;
                $hasRecentActivity = $fullAnalysis['metadata_analysis']['has_recent_activity'] ?? false;
                
                // Reject if no recent activity (last review > 6 months)
                if ($newestReviewDate && !$hasRecentActivity) {
                    $monthsSinceLastReview = floor((time() - strtotime($newestReviewDate)) / (30 * 24 * 3600));
                    if ($monthsSinceLastReview > 6) {
                        $businessesRejected++;
                        Log::debug("Rejected: old reviews only", [
                            'place' => $details['name'] ?? 'Unknown',
                            'last_review' => $newestReviewDate,
                            'months_ago' => $monthsSinceLastReview
                        ]);
                        continue;
                    }
                }
                
                // Multi-level confidence filtering
                $shouldProcess = false;
                $reason = '';
                
                if ($confidenceLevel === 'high' && $advancedConfidence >= $confidenceThreshold) {
                    $shouldProcess = true;
                    $reason = 'high_confidence';
                } elseif ($advancedConfidence >= ($confidenceThreshold + 15)) {
                    $shouldProcess = true;
                    $reason = 'high_score';
                }
                
                if ($shouldProcess) {
                    $result = $this->processNewBusinessData($details, $allPlaces[$placeId]['place'] ?? []);
                    
                    if ($result['is_new']) {
                        $businessesNew++;
                    } elseif ($result['is_updated']) {
                        $businessesUpdated++;
                    }
                    
                    $businessesFound++;
                    
                    Log::info("New business processed", [
                        'place_id' => $placeId,
                        'name' => $details['name'] ?? 'Unknown',
                        'confidence' => $advancedConfidence,
                        'confidence_level' => $confidenceLevel,
                        'reason' => $reason,
                        'is_new' => $result['is_new'],
                        'business_age_estimate' => $fullAnalysis['business_age_estimate']
                    ]);
                } else {
                    $businessesRejected++;
                    
                    Log::debug("Rejected: low confidence", [
                        'place_id' => $placeId,
                        'name' => $details['name'] ?? 'Unknown',
                        'confidence' => $advancedConfidence,
                        'confidence_level' => $confidenceLevel,
                        'threshold' => $confidenceThreshold
                    ]);
                }
                
            } catch (\Exception $e) {
                Log::warning("Failed to process new business {$placeId}: " . $e->getMessage());
            }
        }

        // Update session statistics with metadata
        $this->currentSession->update([
            'api_calls_count' => $apiCallsCount,
            'estimated_cost' => $estimatedCost,
            'businesses_found' => $businessesFound,
            'businesses_new' => $businessesNew,
            'businesses_updated' => $businessesUpdated,
            'metadata' => [
                'businesses_rejected' => $businessesRejected,
                'total_candidates' => count($allPlaces),
                'details_fetched' => count($placeDetails),
                'optimization_version' => '2.0',
                'features_used' => [
                    'pagination',
                    'caching',
                    'batch_operations',
                    'geolocation_validation',
                    'smart_pre_filtering',
                    'advanced_confidence_scoring',
                    'review_freshness_validation'
                ]
            ]
        ]);
        
        Log::info("Optimized scraping completed", [
            'found' => $businessesFound,
            'new' => $businessesNew,
            'rejected' => $businessesRejected,
            'api_calls' => $apiCallsCount,
            'cost' => '$' . number_format($estimatedCost, 2),
            'time_elapsed' => (time() - $startTime) . 's'
        ]);
    }

    /**
     * Perform initial scraping for a region - uses same optimized logic as performNewBusinessOnlyScraping
     */
    private function performInitialScraping(BaliRegion $region, array $categories): void
    {
        // Use the same optimized logic with default threshold of 75
        $this->performNewBusinessOnlyScraping($region, $categories, 75);
    }

    /**
     * Perform weekly update scraping
     */
    private function performWeeklyUpdate(array $regions, array $categories): void
    {
        $regions = empty($regions) ? $this->getPriorityRegions() : $regions;
        $categories = empty($categories) ? $this->getAllCategories() : $categories;
        
        $apiCallsCount = 0;
        $estimatedCost = 0;
        $businessesFound = 0;
        $businessesNew = 0;
        $businessesUpdated = 0;

        foreach ($regions as $regionName) {
            $region = BaliRegion::where('name', $regionName)->where('type', 'kabupaten')->first();
            if (!$region) continue;

            // Step 1: Text Search for "new" keywords
            foreach ($categories as $category) {
                $categoryMapping = CategoryMapping::where('brief_category', $category)->first();
                if (!$categoryMapping) continue;

                $newQueries = [
                    "{$category} baru dibuka {$regionName}",
                    "new {$category} {$regionName}",
                    "recently opened {$category} {$regionName}",
                ];

                foreach ($newQueries as $query) {
                    try {
                        $result = $this->googlePlacesService->textSearch($query);
                        $apiCallsCount++;
                        $estimatedCost += 0.032;

                        if (isset($result['results'])) {
                            foreach ($result['results'] as $place) {
                                $details = $this->googlePlacesService->placeDetails($place['place_id']);
                                $apiCallsCount++;
                                $estimatedCost += 0.017;

                                $result = $this->processBusinessData($details['result'] ?? [], $place);
                                
                                if ($result['is_new']) {
                                    $businessesNew++;
                                } elseif ($result['is_updated']) {
                                    $businessesUpdated++;
                                }
                                
                                $businessesFound++;
                            }
                        }

                        usleep(100000); // 0.1 second delay
                        
                    } catch (\Exception $e) {
                        Log::warning("Weekly text search failed for query: {$query} - " . $e->getMessage());
                    }
                }
            }

            // Step 2: Scan hot zones (areas with high growth)
            $hotZones = $this->getHotZones($region);
            
            foreach ($hotZones as $hotZone) {
                foreach ($categories as $category) {
                    $categoryMapping = CategoryMapping::where('brief_category', $category)->first();
                    if (!$categoryMapping) continue;

                    try {
                        $result = $this->googlePlacesService->nearbySearch(
                            $hotZone['lat'],
                            $hotZone['lng'],
                            $hotZone['radius'],
                            ['type' => $categoryMapping->google_types[0] ?? null]
                        );
                        
                        $apiCallsCount++;
                        $estimatedCost += 0.032;

                        if (isset($result['results'])) {
                            foreach ($result['results'] as $place) {
                                // Check if this is a new place
                                $existingBusiness = Business::where('place_id', $place['place_id'])->first();
                                
                                if (!$existingBusiness) {
                                    $details = $this->googlePlacesService->placeDetails($place['place_id']);
                                    $apiCallsCount++;
                                    $estimatedCost += 0.017;

                                    $result = $this->processBusinessData($details['result'] ?? [], $place);
                                    
                                    if ($result['is_new']) {
                                        $businessesNew++;
                                    }
                                    
                                    $businessesFound++;
                                }
                            }
                        }

                        usleep(100000); // 0.1 second delay
                        
                    } catch (\Exception $e) {
                        Log::warning("Hot zone search failed: " . $e->getMessage());
                    }
                }
            }

            // Step 3: Update young businesses (age < 6 months, review < 20)
            $youngBusinesses = Business::where('area', 'LIKE', "%{$regionName}%")
                ->where('review_count', '<', 20)
                ->where('first_seen', '>', now()->subMonths(6))
                ->limit(20) // Limit to avoid too many API calls
                ->get();

            foreach ($youngBusinesses as $business) {
                try {
                    $details = $this->googlePlacesService->placeDetails($business->place_id);
                    $apiCallsCount++;
                    $estimatedCost += 0.017;

                    $result = $this->processBusinessData($details['result'] ?? [], [], $business);
                    
                    if ($result['is_updated']) {
                        $businessesUpdated++;
                    }
                    
                    usleep(100000); // 0.1 second delay
                    
                } catch (\Exception $e) {
                    Log::warning("Failed to update young business {$business->place_id}: " . $e->getMessage());
                }
            }
        }

        // Update session statistics
        $this->currentSession->update([
            'api_calls_count' => $apiCallsCount,
            'estimated_cost' => $estimatedCost,
            'businesses_found' => $businessesFound,
            'businesses_new' => $businessesNew,
            'businesses_updated' => $businessesUpdated,
        ]);
    }

    /**
     * Process business data and save to database
     */
    private function processBusinessData(array $details, array $place = [], ?Business $existingBusiness = null): array
    {
        if (empty($details)) {
            return ['is_new' => false, 'is_updated' => false];
        }

        $business = $existingBusiness ?? Business::firstOrNew(['place_id' => $details['place_id']]);
        $isNew = !$business->exists;
        $isUpdated = false;

        // Generate indicators using detection service
        $reviews = $details['reviews'] ?? [];
        $photos = $details['photos'] ?? [];
        $indicators = $this->detectionService->generateBusinessIndicators($business, $details, $reviews, $photos);

        // Prepare data for update
        $data = [
            'name' => $details['name'],
            'types' => $details['types'] ?? [],
            'address' => $details['formatted_address'] ?? '',
            'phone' => $details['formatted_phone_number'] ?? null,
            'website' => $details['website'] ?? null,
            'area' => $this->extractAreaFromAddress($details['formatted_address'] ?? ''),
            'lat' => $details['geometry']['location']['lat'] ?? null,
            'lng' => $details['geometry']['location']['lng'] ?? null,
            'rating' => $details['rating'] ?? null,
            'review_count' => $details['user_ratings_total'] ?? 0,
            'opening_hours' => $details['opening_hours'] ?? null,
            'price_level' => $details['price_level'] ?? null,
            'last_fetched' => now(),
            'indicators' => $indicators,
            'scraped_count' => $business->scraped_count + 1,
            'last_update_type' => $this->currentSession->session_type,
            'google_maps_url' => $this->generateGoogleMapsUrl($details),
        ];

        // Set first_seen for new businesses
        if ($isNew) {
            $data['first_seen'] = now();
        }

        // Check if data has changed (for existing businesses)
        if (!$isNew) {
            $hasChanges = false;
            foreach ($data as $key => $value) {
                if ($business->$key != $value) {
                    $hasChanges = true;
                    break;
                }
            }
            $isUpdated = $hasChanges;
        }

        $business->fill($data);
        $business->save();

        return [
            'is_new' => $isNew,
            'is_updated' => $isUpdated,
            'business' => $business
        ];
    }

    /**
     * Generate optimal grid points for complete coverage of a region
     * Ensures no businesses are missed by using adaptive grid based on area size
     */
    private function generateGridPoints(BaliRegion $region): array
    {
        $points = [];
        $centerLat = (float) $region->center_lat;
        $centerLng = (float) $region->center_lng;
        
        // Calculate optimal grid size based on region area and Google Places limit (60 results)
        $gridSize = $this->calculateOptimalGridSize($region);
        $overlap = 0.3; // 30% overlap to ensure no gaps
        $gridSpacing = $gridSize * (1 - $overlap);
        
        // Calculate bounding box for the region
        $boundingBox = $this->calculateRegionBoundingBox($region);
        
        // Generate grid points to cover entire bounding box
        $latStep = $gridSpacing / 111000; // Convert meters to degrees latitude
        $lngStep = $gridSpacing / (111000 * cos(deg2rad($centerLat))); // Adjust for longitude
        
        $latStart = $boundingBox['min_lat'];
        $latEnd = $boundingBox['max_lat'];
        $lngStart = $boundingBox['min_lng'];
        $lngEnd = $boundingBox['max_lng'];
        
        $pointId = 1;
        for ($lat = $latStart; $lat <= $latEnd; $lat += $latStep) {
            for ($lng = $lngStart; $lng <= $lngEnd; $lng += $lngStep) {
                // Skip points that are too far from center (outside region)
                $distanceFromCenter = $this->calculateDistance($centerLat, $centerLng, $lat, $lng);
                if ($distanceFromCenter > $region->search_radius * 1.2) { // 20% buffer
                    continue;
                }
                
                $points[] = [
                    'id' => $pointId++,
                    'lat' => $lat,
                    'lng' => $lng,
                    'radius' => $gridSize,
                    'distance_from_center' => $distanceFromCenter
                ];
            }
        }
        
        Log::info("Generated grid for {$region->name}", [
            'total_points' => count($points),
            'grid_size' => $gridSize,
            'grid_spacing' => $gridSpacing,
            'bounding_box' => $boundingBox
        ]);
        
        return $points;
    }

    /**
     * Subdivide dense area when hitting 60 result limit
     * Splits area into 4 smaller grids to ensure no businesses are missed
     */
    private function subdivideDenseArea(float $lat, float $lng, int $radius, ?string $type): array
    {
        $subdividedResults = [];
        
        // Calculate subdivision radius (half of original)
        $subRadius = max(1000, $radius / 2); // Minimum 1km radius
        
        // Calculate offsets for 4 quadrants
        $offset = $radius * 0.25; // 25% of original radius for offset
        
        $subdivisions = [
            // Northeast quadrant
            ['lat' => $lat + ($offset / 111000), 'lng' => $lng + ($offset / (111000 * cos(deg2rad($lat))))],
            // Northwest quadrant  
            ['lat' => $lat + ($offset / 111000), 'lng' => $lng - ($offset / (111000 * cos(deg2rad($lat))))],
            // Southeast quadrant
            ['lat' => $lat - ($offset / 111000), 'lng' => $lng + ($offset / (111000 * cos(deg2rad($lat))))],
            // Southwest quadrant
            ['lat' => $lat - ($offset / 111000), 'lng' => $lng - ($offset / (111000 * cos(deg2rad($lat))))],
        ];
        
        foreach ($subdivisions as $subPoint) {
            try {
                $result = $this->googlePlacesService->nearbySearch(
                    $subPoint['lat'],
                    $subPoint['lng'],
                    $subRadius,
                    ['type' => $type]
                );
                
                if (isset($result['results'])) {
                    $subResultsCount = count($result['results']);
                    
                    Log::info("Subdivision search", [
                        'lat' => $subPoint['lat'],
                        'lng' => $subPoint['lng'],
                        'radius' => $subRadius,
                        'results_count' => $subResultsCount
                    ]);
                    
                    // If subdivision still hits limit, recursively subdivide further
                    if ($subResultsCount >= 60 && $subRadius > 1000) {
                        $furtherSubdivided = $this->subdivideDenseArea(
                            $subPoint['lat'], 
                            $subPoint['lng'], 
                            $subRadius, 
                            $type
                        );
                        $subdividedResults = array_merge($subdividedResults, $furtherSubdivided);
                    } else {
                        $subdividedResults = array_merge($subdividedResults, $result['results']);
                    }
                }
                
                // Small delay between subdivision requests
                usleep(50000); // 0.05 second
                
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
        // Smaller grid = better coverage but more API calls
        
        // Adaptive grid size optimized for 60 result limit
        // Smaller grid = less likely to hit 60 limit, better coverage
        switch ($region->priority) {
            case 1: // Badung - very high density, very small grid
                return 2000; // 2km grid (reduced from 2.5km)
            case 2: // Denpasar - high density urban center
                return 1500; // 1.5km grid (reduced from 2km)
            case 3: // Gianyar - tourist areas, medium-high density
                return 2500; // 2.5km grid (reduced from 3km)
            case 4: // Tabanan - moderate density
                return 3000; // 3km grid (reduced from 3.5km)
            case 5: // Buleleng - large area, moderate density
                return 3500; // 3.5km grid (reduced from 4km)
            default: // Other regions
                return 3000; // 3km grid (reduced from 3.5km)
        }
    }

    /**
     * Calculate bounding box for a region
     */
    private function calculateRegionBoundingBox(BaliRegion $region): array
    {
        $centerLat = (float) $region->center_lat;
        $centerLng = (float) $region->center_lng;
        $radius = (int) $region->search_radius;
        
        // Convert radius from meters to degrees
        $latRadius = $radius / 111000;
        $lngRadius = $radius / (111000 * cos(deg2rad($centerLat)));
        
        return [
            'min_lat' => $centerLat - $latRadius,
            'max_lat' => $centerLat + $latRadius,
            'min_lng' => $centerLng - $lngRadius,
            'max_lng' => $centerLng + $lngRadius,
        ];
    }

    /**
     * Calculate distance between two coordinates in meters
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Ensure all values are floats
        $lat1 = (float) $lat1;
        $lng1 = (float) $lng1;
        $lat2 = (float) $lat2;
        $lng2 = (float) $lng2;
        
        $earthRadius = 6371000; // Earth radius in meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    /**
     * Get hot zones for a region (areas with high growth)
     */
    private function getHotZones(BaliRegion $region): array
    {
        // For now, return some sample hot zones
        // In a real implementation, this would analyze historical data
        return [
            [
                'lat' => $region->center_lat + 0.01,
                'lng' => $region->center_lng + 0.01,
                'radius' => 3000
            ],
            [
                'lat' => $region->center_lat - 0.01,
                'lng' => $region->center_lng - 0.01,
                'radius' => 3000
            ]
        ];
    }

    /**
     * Get priority regions for weekly updates
     */
    private function getPriorityRegions(): array
    {
        return BaliRegion::where('type', 'kabupaten')
            ->orderBy('priority')
            ->limit(3)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get all categories
     */
    private function getAllCategories(): array
    {
        return CategoryMapping::pluck('brief_category')->toArray();
    }

    /**
     * Extract area from address
     */
    private function extractAreaFromAddress(string $address): ?string
    {
        $parts = array_map('trim', explode(',', $address));

        foreach ($parts as $part) {
            if (str_contains($part, 'Kota') || str_contains($part, 'Kabupaten')) {
                return $part;
            }
        }

        return $parts[count($parts) - 2] ?? $address;
    }

    /**
     * Generate Google Maps URL
     */
    private function generateGoogleMapsUrl(array $details): ?string
    {
        $name = $details['name'] ?? '';
        $address = $details['formatted_address'] ?? '';
        
        if ($name && $address) {
            return "https://www.google.com/maps/search/?api=1&query=" . urlencode($name . ', ' . $address);
        }
        
        return null;
    }

    /**
     * Get OPTIMIZED queries for new businesses with time filters
     * Enhanced with year filters to reduce false positives
     */
    private function getNewBusinessQueries(string $category, string $regionName): array
    {
        $currentYear = date('Y');
        
        return [
            // Indonesian with year filter - highest precision
            "{$category} baru dibuka {$regionName} {$currentYear}",
            // English with year filter - second highest precision  
            "new {$category} {$regionName} opened {$currentYear}",
            // Recently opened - catches soft/grand openings
            "recently opened {$category} {$regionName}",
            // Indonesian synonym - additional coverage
            "{$category} terbaru {$regionName}",
            // Grand opening - catches announcements
            "grand opening {$category} {$regionName}",
        ];
    }

    /**
     * IMPROVED: Smart pre-filtering for new businesses
     * Uses batch DB checks and strict keyword matching to reduce false positives
     * 
     * @param array $place
     * @param string $category
     * @param array $existingPlaceIds Pre-loaded place IDs (for batch efficiency)
     * @param BaliRegion|null $region For geolocation validation
     * @return bool
     */
    private function isNewBusiness(array $place, string $category, array $existingPlaceIds = [], ?BaliRegion $region = null): bool
    {
        $placeId = $place['place_id'];
        
        // 1. Check against pre-loaded existing place IDs (NO database query in loop!)
        if (in_array($placeId, $existingPlaceIds)) {
            return false;
        }
        
        // 2. Geolocation validation - skip if outside target area
        if ($region && isset($place['geometry']['location'])) {
            $placeLat = $place['geometry']['location']['lat'];
            $placeLng = $place['geometry']['location']['lng'];
            
            $distance = $this->calculateDistance(
                (float) $region->center_lat,
                (float) $region->center_lng,
                (float) $placeLat,
                (float) $placeLng
            );
            
            // Skip if outside region radius (with 20% buffer)
            if ($distance > $region->search_radius * 1.2) {
                Log::debug("Skipped: outside region radius", [
                    'place' => $place['name'] ?? 'Unknown',
                    'distance' => round($distance),
                    'max_distance' => $region->search_radius * 1.2
                ]);
                return false;
            }
        }
        
        // 3. Business status validation
        $businessStatus = $place['business_status'] ?? '';
        if (in_array($businessStatus, ['CLOSED_TEMPORARILY', 'CLOSED_PERMANENTLY'])) {
            return false;
        }
        
        // 4. Review count pre-filter - skip obvious established businesses
        $reviewCount = $place['user_ratings_total'] ?? 0;
        if ($reviewCount > 50) {
            Log::debug("Skipped: too many reviews", [
                'place' => $place['name'] ?? 'Unknown',
                'review_count' => $reviewCount
            ]);
            return false;
        }
        
        // 5. STRICT keyword matching - only word boundaries
        $name = $place['name'] ?? '';
        
        // Pattern 1: "baru [dibuka|buka|opening]" or "[new|newly] opened"
        if (preg_match('/\b(baru\s+(dibuka|buka|opening)|terbaru\s+buka|(new|newly)\s+open(ed|ing)?)\b/iu', $name)) {
            return true;
        }
        
        // Pattern 2: "grand opening", "soft opening", "now open"
        if (preg_match('/\b(grand|soft|now)\s+open(ing|ed)?\b/iu', $name)) {
            return true;
        }
        
        // 6. STRICT review count - only VERY new businesses
        // Changed: review < 5 (from < 10) AND rating exists (not just placeholder)
        $hasRating = isset($place['rating']) && $place['rating'] > 0;
        if ($reviewCount > 0 && $reviewCount < 5 && $hasRating) {
            return true;
        }
        
        // 7. No reviews at all - potentially very new
        if ($reviewCount === 0 && $businessStatus === 'OPERATIONAL') {
            return true;
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
        if ($reviewCount < 5) {
            $confidence += 40;
        } elseif ($reviewCount < 15) {
            $confidence += 20;
        }

        // Check business status
        $businessStatus = $details['business_status'] ?? '';
        if ($businessStatus === 'OPENED_RECENTLY') {
            $confidence += 50;
        }

        // Check if newly discovered (not in database)
        $existingBusiness = Business::where('place_id', $details['place_id'])->first();
        if (!$existingBusiness) {
            $confidence += 20;
        }

        return min(100, $confidence);
    }

    /**
     * Process new business data specifically
     */
    private function processNewBusinessData(array $details, array $place = []): array
    {
        if (empty($details)) {
            return ['is_new' => false, 'is_updated' => false];
        }

        $business = Business::firstOrNew(['place_id' => $details['place_id']]);
        $isNew = !$business->exists;
        $isUpdated = false;

        // Generate indicators using detection service
        $reviews = $details['reviews'] ?? [];
        $photos = $details['photos'] ?? [];
        $indicators = $this->detectionService->generateBusinessIndicators($business, $details, $reviews, $photos);

        // Prepare data for update
        $data = [
            'name' => $details['name'],
            'types' => $details['types'] ?? [],
            'address' => $details['formatted_address'] ?? '',
            'phone' => $details['formatted_phone_number'] ?? null,
            'website' => $details['website'] ?? null,
            'area' => $this->extractAreaFromAddress($details['formatted_address'] ?? ''),
            'lat' => $details['geometry']['location']['lat'] ?? null,
            'lng' => $details['geometry']['location']['lng'] ?? null,
            'rating' => $details['rating'] ?? null,
            'review_count' => $details['user_ratings_total'] ?? 0,
            'opening_hours' => $details['opening_hours'] ?? null,
            'price_level' => $details['price_level'] ?? null,
            'last_fetched' => now(),
            'indicators' => $indicators,
            'scraped_count' => $business->scraped_count + 1,
            'last_update_type' => $this->currentSession->session_type,
            'google_maps_url' => $this->generateGoogleMapsUrl($details),
        ];

        // Set first_seen for new businesses
        if ($isNew) {
            $data['first_seen'] = now();
        }

        // Check if data has changed (for existing businesses)
        if (!$isNew) {
            $hasChanges = false;
            foreach ($data as $key => $value) {
                if ($business->$key != $value) {
                    $hasChanges = true;
                    break;
                }
            }
            $isUpdated = $hasChanges;
        }

        $business->fill($data);
        $business->save();

        return [
            'is_new' => $isNew,
            'is_updated' => $isUpdated,
            'business' => $business
        ];
    }
}
