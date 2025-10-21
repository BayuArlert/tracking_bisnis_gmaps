<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class GooglePlacesService
{
    private string $apiKey;
    private int $maxRequestsPerSecond = 50; // Optimized from 10 to 50 for faster scraping
    private int $maxRetries = 2; // Reduced from 3 to 2
    private array $requestCounts = [];
    private array $lastRequestTimes = [];

    public function __construct()
    {
        $this->apiKey = config('services.gmaps.key');
        
        if (empty($this->apiKey)) {
            throw new Exception('Google Maps API key not configured');
        }
    }

    /**
     * Text Search API
     */
    public function textSearch(string $query, array $options = []): array
    {
        $this->rateLimit();
        
        $params = array_merge([
            'query' => $query,
            'key' => $this->apiKey,
        ], $options);

        $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
        
        return $this->makeRequest($url, $params, 'text_search');
    }

    /**
     * Text Search API with Pagination Support
     * Fetches all pages (up to 60 results) instead of just first 20
     * 
     * @param string $query
     * @param array $options
     * @return array All results from all pages combined
     */
    public function textSearchWithPagination(string $query, array $options = []): array
    {
        $allResults = [];
        $nextPageToken = null;
        $pageCount = 0;
        $maxPages = 3; // Google Places API returns max 60 results (3 pages × 20)
        
        do {
            // Add pagetoken to options if available
            if ($nextPageToken) {
                $options['pagetoken'] = $nextPageToken;
                // Google requires 2 second delay between pagination requests
                sleep(2);
            }
            
            $result = $this->textSearch($query, $options);
            
            // Merge results
            if (isset($result['results']) && is_array($result['results'])) {
                $allResults = array_merge($allResults, $result['results']);
            }
            
            // Get next page token
            $nextPageToken = $result['next_page_token'] ?? null;
            $pageCount++;
            
            // Log pagination progress
            if ($nextPageToken) {
                Log::debug("Fetching page " . ($pageCount + 1) . " for query: {$query}");
            }
            
        } while ($nextPageToken && $pageCount < $maxPages);
        
        Log::info("Text search pagination completed", [
            'query' => $query,
            'pages_fetched' => $pageCount,
            'total_results' => count($allResults)
        ]);
        
        return [
            'results' => $allResults,
            'status' => $result['status'] ?? 'OK',
            'pages_fetched' => $pageCount
        ];
    }

    /**
     * Nearby Search API
     */
    public function nearbySearch(float $lat, float $lng, int $radius, array $options = []): array
    {
        $this->rateLimit();
        
        $params = array_merge([
            'location' => "{$lat},{$lng}",
            'radius' => $radius,
            'key' => $this->apiKey,
        ], $options);

        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
        
        return $this->makeRequest($url, $params, 'nearby_search');
    }

    /**
     * Place Details API
     */
    public function placeDetails(string $placeId, array $fields = []): array
    {
        $this->rateLimit();
        
        $defaultFields = [
            'name', 'rating', 'user_ratings_total', 'types', 'formatted_address',
            'geometry', 'business_status', 'photos', 'reviews', 'place_id',
            'formatted_phone_number', 'website', 'opening_hours', 'price_level'
        ];
        
        $fields = empty($fields) ? $defaultFields : $fields;
        
        $params = [
            'place_id' => $placeId,
            'fields' => implode(',', $fields),
            'key' => $this->apiKey,
        ];

        $url = 'https://maps.googleapis.com/maps/api/place/details/json';
        
        return $this->makeRequest($url, $params, 'place_details');
    }

    /**
     * Get optimized fields for new business detection (Basic tier only - $0.017)
     * Excludes Contact Data (+$0.003) and Atmosphere Data (+$0.005)
     */
    public function getBasicFieldsForNewBusinessDetection(): array
    {
        return [
            // Basic Data (included in $0.017 base price)
            'name',
            'rating',
            'user_ratings_total',
            'geometry',
            'business_status',
            'reviews',
            'photos',
            'place_id',
            'types',
            'formatted_address',
            'editorial_summary'
        ];
    }

    /**
     * Get full fields (all tiers - $0.025)
     * Use only for confirmed new businesses
     */
    public function getFullFields(): array
    {
        return [
            // Basic Data
            'name', 'rating', 'user_ratings_total', 'geometry', 'business_status',
            'reviews', 'photos', 'place_id', 'types', 'formatted_address',
            // Contact Data (+$0.003)
            'formatted_phone_number', 'website', 'opening_hours',
            // Atmosphere Data (+$0.005)
            'price_level'
        ];
    }

    /**
     * Batch Place Details for multiple place IDs
     */
    public function batchPlaceDetails(array $placeIds, array $fields = []): array
    {
        $results = [];
        $batchSize = 20; // Process in batches to avoid overwhelming the API
        
        $chunks = array_chunk($placeIds, $batchSize);
        
        foreach ($chunks as $chunk) {
            $batchResults = $this->processBatch($chunk, $fields);
            $results = array_merge($results, $batchResults);
            
            // Small delay between batches
            usleep(100000); // 0.1 second
        }
        
        return $results;
    }

    /**
     * Process a batch of place IDs
     */
    private function processBatch(array $placeIds, array $fields): array
    {
        $results = [];
        
        foreach ($placeIds as $placeId) {
            try {
                $result = $this->placeDetails($placeId, $fields);
                if (isset($result['result'])) {
                    $results[$placeId] = $result['result'];
                }
            } catch (Exception $e) {
                Log::warning("Failed to fetch details for place {$placeId}: " . $e->getMessage());
                $results[$placeId] = null;
            }
        }
        
        return $results;
    }

    /**
     * Make HTTP request with retry logic and rate limiting
     */
    private function makeRequest(string $url, array $params, string $requestType): array
    {
        $attempt = 0;
        
        while ($attempt < $this->maxRetries) {
            try {
                $response = Http::withOptions(['verify' => false])
                    ->timeout(30)
                    ->get($url, $params);
                
                if (!$response->successful()) {
                    throw new Exception("HTTP error: " . $response->status());
                }
                
                $data = $response->json();
                
                if (isset($data['error_message'])) {
                    throw new Exception("Google API error: " . $data['error_message']);
                }
                
                // Track API usage
                $this->trackApiUsage($requestType);
                
                return $data;
                
            } catch (Exception $e) {
                $attempt++;
                
                if ($attempt >= $this->maxRetries) {
                    Log::error("Google Places API request failed after {$this->maxRetries} attempts: " . $e->getMessage());
                    throw $e;
                }
                
                // Exponential backoff with max delay limit
                $delay = min(pow(2, $attempt) * 500000, 2000000); // Max 2 seconds delay
                usleep($delay);
                
                Log::warning("Google Places API request failed (attempt {$attempt}): " . $e->getMessage());
            }
        }
        
        throw new Exception("Max retries exceeded");
    }

    /**
     * Rate limiting to prevent exceeding API quotas
     */
    private function rateLimit(): void
    {
        $currentTime = microtime(true);
        $currentSecond = floor($currentTime);
        
        // Initialize counters if not exists
        if (!isset($this->requestCounts[$currentSecond])) {
            $this->requestCounts[$currentSecond] = 0;
        }
        
        // Clean old counters (older than 1 second)
        $this->requestCounts = array_filter($this->requestCounts, function($timestamp) use ($currentSecond) {
            return $timestamp >= $currentSecond - 1;
        }, ARRAY_FILTER_USE_KEY);
        
        // Check if we've exceeded the rate limit
        $totalRequests = array_sum($this->requestCounts);
        
        if ($totalRequests >= $this->maxRequestsPerSecond) {
            $sleepTime = 1 - ($currentTime - $currentSecond);
            if ($sleepTime > 0) {
                usleep($sleepTime * 1000000);
            }
        }
        
        $this->requestCounts[$currentSecond]++;
        $this->lastRequestTimes[] = $currentTime;
    }

    /**
     * Track API usage for cost monitoring
     */
    private function trackApiUsage(string $requestType): void
    {
        $costs = [
            'text_search' => 0.032,
            'nearby_search' => 0.032,
            'place_details' => 0.017,
        ];
        
        $cost = $costs[$requestType] ?? 0;
        
        // Store in cache for cost tracking
        $key = 'google_places_api_usage_' . date('Y-m-d');
        $usage = Cache::get($key, [
            'text_search' => ['count' => 0, 'cost' => 0],
            'nearby_search' => ['count' => 0, 'cost' => 0],
            'place_details' => ['count' => 0, 'cost' => 0],
            'total_cost' => 0,
        ]);
        
        $usage[$requestType]['count']++;
        $usage[$requestType]['cost'] += $cost;
        $usage['total_cost'] += $cost;
        
        Cache::put($key, $usage, 86400); // 24 hours
    }

    /**
     * Get API usage statistics
     */
    public function getApiUsage(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $key = 'google_places_api_usage_' . $date;
        
        return Cache::get($key, [
            'text_search' => ['count' => 0, 'cost' => 0],
            'nearby_search' => ['count' => 0, 'cost' => 0],
            'place_details' => ['count' => 0, 'cost' => 0],
            'total_cost' => 0,
        ]);
    }

    /**
     * Get monthly API usage
     */
    public function getMonthlyApiUsage(): array
    {
        $month = date('Y-m');
        $usage = [
            'text_search' => ['count' => 0, 'cost' => 0],
            'nearby_search' => ['count' => 0, 'cost' => 0],
            'place_details' => ['count' => 0, 'cost' => 0],
            'total_cost' => 0,
        ];
        
        // Sum up all days in the month
        for ($day = 1; $day <= 31; $day++) {
            $date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $dayUsage = $this->getApiUsage($date);
            
            foreach ($usage as $type => $data) {
                if (isset($dayUsage[$type])) {
                    $usage[$type]['count'] += $dayUsage[$type]['count'];
                    $usage[$type]['cost'] += $dayUsage[$type]['cost'];
                }
            }
        }
        
        return $usage;
    }

    /**
     * Estimate cost for a scraping operation
     */
    public function estimateCost(array $operations): float
    {
        $costs = [
            'text_search' => 0.032,
            'nearby_search' => 0.032,
            'place_details' => 0.017,
        ];
        
        $totalCost = 0;
        
        foreach ($operations as $operation => $count) {
            if (isset($costs[$operation])) {
                $totalCost += $count * $costs[$operation];
            }
        }
        
        return $totalCost;
    }

    /**
     * Check if we're approaching budget limit
     */
    public function isApproachingBudgetLimit(float $budgetLimit = 300.0, float $threshold = 0.9): bool
    {
        $monthlyUsage = $this->getMonthlyApiUsage();
        $currentCost = $monthlyUsage['total_cost'];
        
        return $currentCost >= ($budgetLimit * $threshold);
    }

    /**
     * Get remaining budget
     */
    public function getRemainingBudget(float $budgetLimit = 300.0): float
    {
        $monthlyUsage = $this->getMonthlyApiUsage();
        $currentCost = $monthlyUsage['total_cost'];
        
        return max(0, $budgetLimit - $currentCost);
    }

    /**
     * Reset API usage tracking (for testing)
     */
    public function resetApiUsage(): void
    {
        $key = 'google_places_api_usage_' . date('Y-m-d');
        Cache::forget($key);
    }
}
