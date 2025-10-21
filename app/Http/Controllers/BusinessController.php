<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $query = Business::query();


        // Apply filters
        if ($request->has('area') && $request->area !== 'all' && $request->area !== '') {
            // Find original area names that match the clean area
            $originalAreas = $this->getOriginalAreaNames($request->area);
            $query->whereIn('area', $originalAreas);
        }

        if ($request->has('category') && $request->category !== 'all' && $request->category !== '') {
            // Find original category names that match the clean category
            $originalCategories = $this->getOriginalCategoryNames($request->category);
            $query->whereIn('category', $originalCategories);
        }

        if ($request->has('data_age') && $request->data_age !== 'all' && $request->data_age !== '') {
            $this->applyDataAgeFilter($query, $request->data_age);
        }

        // Apply hierarchical location filters (Kabupaten/Kecamatan)
        if ($request->has('kabupaten') && $request->kabupaten !== '') {
            // Check both area field and address field for kabupaten with multiple format variations
            $query->where(function($q) use ($request) {
                $kabupaten = $request->kabupaten;
                
                // Try different formats: "Kabupaten X", "Kota X", "X" (case insensitive)
                $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kabupaten ' . strtolower($kabupaten) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kota ' . strtolower($kabupaten) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ',%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ' %']);
            });
        }
        
        if ($request->has('kecamatan') && $request->kecamatan !== '') {
            // Check both area field and address field for kecamatan with multiple format variations
            $query->where(function($q) use ($request) {
                $kecamatan = $request->kecamatan;
                
                // Try different formats: "Kecamatan X", "Kec. X", "X" (case insensitive)
                $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kecamatan ' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ',%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ' %']);
            });
        }

        // Apply radius filter if explicitly requested
        if ($request->has('use_radius') && $request->use_radius == 'true' 
            && $request->has('radius') && $request->has('center_lat') && $request->has('center_lng')) {
            $this->applyRadiusFilter($query, $request->center_lat, $request->center_lng, $request->radius);
        }

        // Get total count before pagination
        $total = $query->count();

        // Apply pagination - allow larger limits for client-side pagination
        $skip = $request->get('skip', 0);
        $limit = $request->get('limit', 20);
        
        // Set a reasonable maximum limit to prevent memory issues (50000 should be enough)
        $limit = min($limit, 50000);

        $businesses = $query->latest('first_seen')->skip($skip)->take($limit)->get();


        return response()->json([
            'data' => $businesses,
            'total' => $total,
            'count' => $businesses->count(),
            'skip' => (int) $skip,
            'limit' => (int) $limit,
        ]);
    }


    public function getFilterOptions()
    {
        $rawAreas = Business::select('area')
            ->distinct()
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->orderBy('area')
            ->pluck('area');

        $rawCategories = Business::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->pluck('category');

        // Clean and format areas - FOCUS ON BADUNG ONLY
        $areas = [];
        foreach ($rawAreas as $area) {
            $cleanArea = $this->cleanAreaName($area);
            // Only include Badung areas (filter out null and non-Badung)
            if ($cleanArea && !in_array($cleanArea, $areas)) {
                $areas[] = $cleanArea;
            }
        }
        sort($areas);

        // Clean and format categories
        $categories = [];
        foreach ($rawCategories as $category) {
            $cleanCategory = $this->cleanCategoryName($category);
            if (!in_array($cleanCategory, $categories)) {
                $categories[] = $cleanCategory;
            }
        }
        sort($categories);

        return response()->json([
            'areas' => $areas,
            'categories' => $categories,
        ]);
    }

    private function cleanAreaName($area)
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

    private function cleanCategoryName($category)
    {
        // Convert snake_case to Title Case
        // "beauty_salon" -> "Beauty Salon"
        $clean = str_replace('_', ' ', $category);
        $clean = ucwords($clean);
        
        // Handle specific cases
        $mapping = [
            'Doctor' => 'Doctor',
            'Establishment' => 'Establishment',
            'Bar' => 'Bar',
            'Campground' => 'Campground',
            'Convenience Store' => 'Convenience Store',
            'Car Repair' => 'Car Repair',
            'Beauty Salon' => 'Beauty Salon',
        ];
        
        return $mapping[$clean] ?? $clean;
    }

    private function getOriginalAreaNames($cleanArea)
    {
        $allAreas = Business::select('area')
            ->distinct()
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->pluck('area');

        $originalAreas = [];
        foreach ($allAreas as $area) {
            if ($this->cleanAreaName($area) === $cleanArea) {
                $originalAreas[] = $area;
            }
        }

        return $originalAreas;
    }

    private function getOriginalCategoryNames($cleanCategory)
    {
        $allCategories = Business::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->pluck('category');

        $originalCategories = [];
        foreach ($allCategories as $category) {
            if ($this->cleanCategoryName($category) === $cleanCategory) {
                $originalCategories[] = $category;
            }
        }

        return $originalCategories;
    }

    private function applyDataAgeFilter($query, $dataAge)
    {
        // Coba filter berdasarkan metadata_analysis terlebih dahulu
        $hasMetadata = $query->whereNotNull('indicators->metadata_analysis->business_age_estimate');
        
        switch ($dataAge) {
            case 'ultra_new':
                // Data baru (< 1 minggu) - berdasarkan metadata atau first_seen
                $query->where(function($q) {
                    $q->whereJsonContains('indicators->metadata_analysis->business_age_estimate', 'ultra_new')
                      ->orWhere('first_seen', '>=', now()->subDays(7));
                });
                break;
                
            case 'very_new':
                // Data baru (< 1 bulan)
                $query->where(function($q) {
                    $q->whereJsonContains('indicators->metadata_analysis->business_age_estimate', 'very_new')
                      ->orWhere('first_seen', '>=', now()->subDays(30));
                });
                break;
                
            case 'new':
                // Data baru (< 3 bulan)
                $query->where(function($q) {
                    $q->whereJsonContains('indicators->metadata_analysis->business_age_estimate', 'new')
                      ->orWhere('first_seen', '>=', now()->subDays(90));
                });
                break;
                
            case 'recent':
                // Data recent (< 12 bulan)
                $query->where(function($q) {
                    $q->whereJsonContains('indicators->metadata_analysis->business_age_estimate', 'recent')
                      ->orWhere('first_seen', '>=', now()->subDays(365));
                });
                break;
                
            case 'established':
                // Data established (1-3 tahun)
                $query->where(function($q) {
                    $q->whereJsonContains('indicators->metadata_analysis->business_age_estimate', 'established')
                      ->orWhereBetween('first_seen', [now()->subDays(1095), now()->subDays(365)]);
                });
                break;
                
            case 'old':
                // Data lama (> 3 tahun)
                $query->where(function($q) {
                    $q->whereJsonContains('indicators->metadata_analysis->business_age_estimate', 'old')
                      ->orWhere('first_seen', '<', now()->subDays(1095));
                });
                break;
        }
    }

    private function applyRadiusFilter($query, $centerLat, $centerLng, $radius)
    {
        // Use Haversine formula to calculate distance
        $query->selectRaw("*, 
            (6371 * acos(cos(radians(?)) 
            * cos(radians(lat)) 
            * cos(radians(lng) - radians(?)) 
            + sin(radians(?)) 
            * sin(radians(lat)))) AS distance", 
            [$centerLat, $centerLng, $centerLat])
            ->having('distance', '<=', $radius / 1000) // Convert meters to kilometers
            ->orderBy('distance');
    }

    public function exportCSV()
    {
        $businesses = Business::all();
        
        $csvData = "ID,Nama,Kategori,Area,Alamat,Rating,Review Count,Phone,First Seen,Lat,Lng,Google Maps URL,Recently Opened,Few Reviews,Has Recent Photo\n";
        
        foreach ($businesses as $business) {
            $indicators = $business->indicators ?? [];
            $csvData .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",%s,%d,\"%s\",%s,%s,%s,\"%s\",%s,%s,%s\n",
                $business->id,
                $business->name,
                $business->category,
                $business->area,
                $business->address,
                $business->rating ?? '',
                $business->review_count,
                $business->phone ?? '',
                $business->first_seen,
                $business->lat ?? '',
                $business->lng ?? '',
                $business->google_maps_url ?? '',
                $indicators['recently_opened'] ? 'Yes' : 'No',
                $indicators['few_reviews'] ? 'Yes' : 'No',
                $indicators['has_recent_photo'] ? 'Yes' : 'No'
            );
        }
        
        $filename = 'businesses_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function fetchNew(Request $request)
    {
        // Increase execution time limit to prevent timeout
        set_time_limit(1800); // 30 minutes for comprehensive scraping
        
        $request->validate([
            'area' => 'required|string|max:100',
            'categories' => 'nullable|array',
            'categories.*' => 'string|in:Café,Restoran,Sekolah,Villa,Hotel,Popular Spot,Lainnya',
        ]);

        try {
            // Use the new ScrapingOrchestratorService for comprehensive scraping
            $scrapingOrchestrator = app(\App\Services\ScrapingOrchestratorService::class);
            
            $area = $request->area;
            $categories = $request->categories ?? ['Café']; // Default to Café if no categories specified
            
            Log::info("Starting manual scraping", [
                'area' => $area,
                'categories' => $categories
            ]);
            
            // Start initial scraping session
            $session = $scrapingOrchestrator->startInitialScraping($area, $categories);
            
            // Get businesses that were found in this session
            $newBusinesses = Business::where('last_update_type', 'initial')
                ->where('area', 'LIKE', "%{$area}%")
                ->where('last_fetched', '>=', $session->started_at)
                ->get();
            
            return response()->json([
                'success' => true,
                'method' => 'comprehensive_grid_search',
                'strategy' => 'Adaptive grid search with optimal coverage',
                'session' => [
                    'id' => $session->id,
                    'area' => $session->target_area,
                    'categories' => $session->target_categories,
                    'api_calls_count' => $session->api_calls_count,
                    'estimated_cost' => $session->estimated_cost,
                    'businesses_found' => $session->businesses_found,
                    'businesses_new' => $session->businesses_new,
                    'businesses_updated' => $session->businesses_updated,
                    'duration_seconds' => $session->duration,
                ],
                'businesses' => $newBusinesses,
                'message' => 'Scraping completed successfully with comprehensive coverage'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Manual scraping failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Scraping failed: ' . $e->getMessage(),
                'message' => 'Please check the logs for more details'
            ], 500);
        }
    }


    private function extractAreaFromAddress(string $address): ?string
    {
        $parts = array_map('trim', explode(',', $address));

        foreach ($parts as $part) {
            if (str_contains($part, 'Kota') || str_contains($part, 'Kabupaten')) {
                return $part;
            }
        }

        // fallback ambil part terakhir kedua
        return $parts[count($parts) - 2] ?? $address;
    }

    /**
     * Get preview area (convex hull) for a cluster of businesses
     */
    public function getPreviewArea(Request $request)
    {
        $request->validate([
            'business_ids' => 'required|array',
            'business_ids.*' => 'integer|exists:businesses,id',
        ]);

        $businesses = Business::whereIn('id', $request->business_ids)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get();

        if ($businesses->count() < 3) {
            return response()->json([
                'error' => 'Need at least 3 businesses for preview area'
            ], 400);
        }

        // Get coordinates
        $points = $businesses->map(function ($business) {
            return [
                'lat' => (float) $business->lat,
                'lng' => (float) $business->lng,
            ];
        })->toArray();

        // Calculate centroid
        $centroid = [
            'lat' => array_sum(array_column($points, 'lat')) / count($points),
            'lng' => array_sum(array_column($points, 'lng')) / count($points),
        ];

        // Calculate max radius
        $maxRadius = 0;
        foreach ($points as $point) {
            $distance = $this->calculateDistance(
                $centroid['lat'], 
                $centroid['lng'], 
                $point['lat'], 
                $point['lng']
            );
            if ($distance > $maxRadius) {
                $maxRadius = $distance;
            }
        }

        return response()->json([
            'businesses_count' => $businesses->count(),
            'center' => $centroid,
            'radius' => $maxRadius,
            'points' => $points,
            'category' => $businesses->first()->category ?? 'businesses',
            'area' => $businesses->first()->area ?? 'Unknown',
        ]);
    }


    private function generateBusinessIndicators($info, $business)
    {
        $reviewCount = $info['user_ratings_total'] ?? 0;
        $rating = $info['rating'] ?? 0;
        $businessStatus = $info['business_status'] ?? 'OPERATIONAL';
        $photos = $info['photos'] ?? [];
        $reviews = $info['reviews'] ?? [];
        
        // Detect status changes
        $statusChange = $this->detectStatusChange($business, $businessStatus);
        
        // Analisis foto (new detailed analysis)
        $photoAnalysis = $this->hasRecentPhoto($photos);
        
        // Extract social links & website
        $socialLinks = $this->extractSocialLinks($info, $business);
        
        // Analisis metadata untuk menentukan usia bisnis
        $metadataAnalysis = $this->analyzeBusinessMetadata($info, $reviews, $photos);
        // Add photo analysis to metadata
        $metadataAnalysis['photo_analysis'] = $photoAnalysis;
        // Add social links to metadata
        $metadataAnalysis['social_links'] = $socialLinks;
        // Add status change info to metadata
        $metadataAnalysis['status_change'] = $statusChange;
        
        // Logic untuk mendeteksi bisnis baru berdasarkan metadata
        $indicators = [
            'recently_opened' => $this->detectRecentlyOpenedFromMetadata($businessStatus, $metadataAnalysis),
            'few_reviews' => $reviewCount < 15,
            'low_rating_count' => $reviewCount < 5,
            'review_count' => $reviewCount, // For scoring calculations
            'has_photos' => count($photos) > 0,
            'has_recent_photo' => $photoAnalysis['has_recent'],
            'photo_details' => $photoAnalysis, // Detailed photo info
            'has_website' => $socialLinks['has_website'],
            'has_social' => $socialLinks['has_social'],
            'social_links' => $socialLinks, // Social media links
            'business_status' => $businessStatus, // Save current status for tracking
            'status_changed' => $statusChange['status_changed'],
            'is_new_operational' => $statusChange['is_new_operational'],
            'rating_improvement' => $this->detectRatingImprovement($business, $rating),
            'review_spike' => $this->detectReviewSpike($business, $reviewCount),
            'is_truly_new' => $this->isTrulyNewBusinessFromMetadata($metadataAnalysis, $business),
            'newly_discovered' => !$business->exists,
            'metadata_analysis' => $metadataAnalysis,
        ];
        
        // Hitung confidence score untuk bisnis baru
        $indicators['new_business_confidence'] = $this->calculateNewBusinessConfidenceFromMetadata($indicators, $metadataAnalysis, $business);
        
        return $indicators;
    }


    private function detectRatingImprovement($business, $currentRating)
    {
        if (!$business->exists || !$business->rating) {
            return false;
        }
        
        // Jika rating naik signifikan dari sebelumnya
        return $currentRating > $business->rating + 0.5;
    }

    private function detectReviewSpike($business, $currentReviewCount)
    {
        if (!$business->exists) {
            return false;
        }
        
        // Brief requirement: Review burst >40% dalam 30 hari terakhir
        $lastUpdate = $business->last_fetched ?? $business->updated_at;
        $daysSinceUpdate = now()->diffInDays($lastUpdate);
        
        // Must be within 30 days to be considered a spike
        if ($daysSinceUpdate > 30) {
            return false;
        }
        
        $previousReviewCount = $business->review_count ?? 0;
        
        // If no previous reviews, any new reviews is a spike
        if ($previousReviewCount === 0 && $currentReviewCount > 0) {
            return true;
        }
        
        if ($previousReviewCount > 0) {
            $newReviews = $currentReviewCount - $previousReviewCount;
            $growth = ($newReviews / $previousReviewCount) * 100;
            
            // Brief: >40% OR >10 new reviews in 30 days
            return $growth > 40 || $newReviews >= 10;
        }
        
        return false;
    }


    private function analyzeBusinessMetadata($info, $reviews, $photos)
    {
        $analysis = [
            'oldest_review_date' => null,
            'newest_review_date' => null,
            'review_age_months' => null,
            'photo_count' => count($photos),
            'has_recent_activity' => false,
            'business_age_estimate' => 'unknown',
            'confidence_level' => 'low',
        ];

        // Analisis review dates
        if (!empty($reviews)) {
            $reviewDates = [];
            foreach ($reviews as $review) {
                if (isset($review['time'])) {
                    $reviewDates[] = $review['time'];
                }
            }

            if (!empty($reviewDates)) {
                sort($reviewDates);
                $oldestReview = min($reviewDates);
                $newestReview = max($reviewDates);

                $analysis['oldest_review_date'] = date('Y-m-d', $oldestReview);
                $analysis['newest_review_date'] = date('Y-m-d', $newestReview);
                
                // Hitung usia dalam bulan
                $monthsDiff = (time() - $oldestReview) / (30 * 24 * 60 * 60);
                $analysis['review_age_months'] = floor($monthsDiff);

                // Hitung usia dalam hari untuk granularity mingguan
                $daysDiff = (time() - $oldestReview) / (24 * 60 * 60);
                
                // Tentukan estimasi usia bisnis dengan granularity lebih detail
                if ($daysDiff < 7) {
                    $analysis['business_age_estimate'] = 'ultra_new';
                    $analysis['confidence_level'] = 'high';
                } elseif ($daysDiff < 30) {
                    $analysis['business_age_estimate'] = 'very_new';
                    $analysis['confidence_level'] = 'high';
                } elseif ($monthsDiff < 3) {
                    $analysis['business_age_estimate'] = 'new';
                    $analysis['confidence_level'] = 'high';
                } elseif ($monthsDiff < 12) {
                    $analysis['business_age_estimate'] = 'recent';
                    $analysis['confidence_level'] = 'medium';
                } elseif ($monthsDiff < 36) {
                    $analysis['business_age_estimate'] = 'established';
                    $analysis['confidence_level'] = 'high';
                } else {
                    $analysis['business_age_estimate'] = 'old';
                    $analysis['confidence_level'] = 'high';
                }

                // Cek aktivitas recent (review dalam 3 bulan terakhir)
                $threeMonthsAgo = time() - (3 * 30 * 24 * 60 * 60);
                $analysis['has_recent_activity'] = $newestReview > $threeMonthsAgo;
            }
        }

        // Analisis foto (jika ada metadata foto)
        if (!empty($photos)) {
            // Untuk saat ini, kita anggap bisnis dengan banyak foto = lebih established
            if (count($photos) > 10) {
                $analysis['business_age_estimate'] = 'established';
                $analysis['confidence_level'] = 'medium';
            }
        }

        return $analysis;
    }

    private function detectRecentlyOpenedFromMetadata($businessStatus, $metadataAnalysis)
    {
        // Google Maps API memberikan status "OPENED_RECENTLY" - ini yang paling akurat
        if ($businessStatus === 'OPENED_RECENTLY') {
            return true;
        }

        // Fallback: Jika business_status tidak tersedia atau tidak valid
        if (empty($businessStatus) || !in_array($businessStatus, ['OPERATIONAL', 'CLOSED_TEMPORARILY', 'CLOSED_PERMANENTLY', 'OPENED_RECENTLY'])) {
            // Gunakan metadata analysis sebagai fallback
            if (in_array($metadataAnalysis['business_age_estimate'], ['ultra_new', 'very_new', 'new'])) {
                return true;
            }
        }

        // Jika review pertama < 3 bulan, kemungkinan baru
        if (in_array($metadataAnalysis['business_age_estimate'], ['ultra_new', 'very_new', 'new'])) {
            return true;
        }

        return false;
    }

    private function isTrulyNewBusinessFromMetadata($metadataAnalysis, $business)
    {
        // Jika Google Maps menunjukkan "OPENED_RECENTLY", pasti baru
        if (in_array($metadataAnalysis['business_age_estimate'], ['ultra_new', 'very_new'])) {
            return true;
        }

        // Jika review pertama < 6 bulan dan confidence tinggi
        if ($metadataAnalysis['business_age_estimate'] === 'new' && 
            $metadataAnalysis['confidence_level'] === 'high') {
            return true;
        }

        // Jika tidak ada review sama sekali dan baru ditemukan
        if (is_null($metadataAnalysis['oldest_review_date']) && !$business->exists) {
            return true;
        }

        return false;
    }

    private function calculateNewBusinessConfidenceFromMetadata($indicators, $metadataAnalysis, $business)
    {
        // PRODUCTION-GRADE SCORING: Component-based weighted system
        // This prevents point inflation and provides more accurate scoring
        
        // Step 1: Validate indicator consistency (catch data quality issues)
        $validated = $this->validateIndicatorConsistency($indicators, $metadataAnalysis, $business);
        $indicators = $validated['indicators'];
        $warnings = $validated['warnings'];
        
        // Step 2: Calculate component scores (max 100 each)
        $ageScore = $this->calculateAgeComponentScore($metadataAnalysis);
        $signalsScore = $this->calculateSignalsComponentScore($indicators);
        $activityScore = $this->calculateActivityComponentScore($indicators);
        
        // Step 3: Calculate penalties for negative indicators
        $penalties = $this->calculatePenalties($indicators, $metadataAnalysis, $business);
        
        // Step 4: Weighted combination (prevents easy 100-cap hits)
        $baseScore = 
            ($ageScore * 0.45) +        // Age is most important (45%)
            ($signalsScore * 0.35) +    // Signals are secondary (35%)
            ($activityScore * 0.20);    // Activity is tertiary (20%)
        
        // Step 5: Apply penalties
        $finalScore = max(0, $baseScore - $penalties);
        
        // Step 6: Combo bonus (only if score already good)
        if ($finalScore >= 60) {
            $positiveCount = $this->countPositiveIndicators($indicators);
            if ($positiveCount >= 5) {
                $finalScore = min(100, $finalScore + 8); // Reduced bonus
            }
        }
        
        // Store validation warnings for debugging
        if (!empty($warnings)) {
            Log::warning('Business confidence calculation warnings', [
                'business_id' => $business->id ?? 'new',
                'warnings' => $warnings
            ]);
        }
        
        return (int) min(100, $finalScore);
    }
    
    /**
     * Validate indicator consistency and catch impossible combinations
     */
    private function validateIndicatorConsistency($indicators, $metadataAnalysis, $business): array
    {
        $warnings = [];
        
        // Check 1: Old business cannot be "recently opened"
        if ($metadataAnalysis['business_age_estimate'] === 'old' && 
            $indicators['recently_opened']) {
            $warnings[] = 'Conflict: Old business marked as recently opened';
            $indicators['recently_opened'] = false; // Override
        }
        
        // Check 2: Ultra new business with too many reviews is suspicious
        if (in_array($metadataAnalysis['business_age_estimate'], ['ultra_new', 'very_new']) && 
            $business->review_count > 100) {
            $warnings[] = 'Suspicious: Very new business with 100+ reviews';
            $metadataAnalysis['confidence_level'] = 'low'; // Downgrade
        }
        
        // Check 3: Review spike requires reviews
        if ($indicators['review_spike'] && $business->review_count < 5) {
            $warnings[] = 'Invalid: Review spike with <5 reviews';
            $indicators['review_spike'] = false;
        }
        
        // Check 4: Established/old business cannot be "newly discovered" and "ultra_new"
        if (in_array($metadataAnalysis['business_age_estimate'], ['established', 'old']) && 
            $indicators['newly_discovered']) {
            $warnings[] = 'Inconsistent: Established business marked as newly discovered';
            // Keep newly_discovered (might be first import) but note the conflict
        }
        
        return [
            'indicators' => $indicators,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Calculate age component score (0-100)
     */
    private function calculateAgeComponentScore($metadataAnalysis): float
    {
        $ageScore = match($metadataAnalysis['business_age_estimate']) {
            'ultra_new' => 95,      // <7 days - very high confidence
            'very_new' => 85,       // <30 days - high confidence
            'new' => 70,            // <90 days - good confidence
            'recent' => 45,         // <1 year - moderate
            'established' => 20,    // 1-3 years - low
            'old' => 0,             // >3 years - not new
            default => 0
        };
        
        // Adjust by confidence level
        $confidenceMultiplier = match($metadataAnalysis['confidence_level'] ?? 'medium') {
            'high' => 1.0,
            'medium' => 0.85,
            'low' => 0.65,
            default => 0.85
        };
        
        return $ageScore * $confidenceMultiplier;
    }
    
    /**
     * Calculate signals component score (0-100) - NON-redundant
     */
    private function calculateSignalsComponentScore($indicators): float
    {
        $score = 0;

        // Google's official signal (most trustworthy)
        if ($indicators['recently_opened']) {
            $score += 35; // High weight for official signal
        }
        
        // Review-based signal (MUTUALLY EXCLUSIVE - no overlap)
        $reviewCount = $indicators['review_count'] ?? 999;
        $reviewScore = match(true) {
            $reviewCount < 5 => 30,   // Very few reviews
            $reviewCount < 15 => 20,  // Few reviews
            $reviewCount < 30 => 10,  // Some reviews
            default => 0
        };
        $score += $reviewScore;
        
        // Status change signal
        if ($indicators['is_new_operational']) {
            $score += 25; // Just became operational - strong signal
        } elseif ($indicators['status_changed']) {
            $score += 12; // Status changed - moderate signal
        }
        
        // Discovery signal
        if ($indicators['newly_discovered']) {
            $score += 10; // First time in our database
        }
        
        return min(100, $score);
    }
    
    /**
     * Calculate activity component score (0-100)
     */
    private function calculateActivityComponentScore($indicators): float
    {
        $score = 0;
        
        // Review activity (non-overlapping with signals)
        if ($indicators['review_spike']) {
            $score += 30; // Strong activity signal
        }
        
        if ($indicators['rating_improvement']) {
            $score += 15; // Getting better
        }
        
        // Photo activity
        if (isset($indicators['photo_details'])) {
            $photoDetails = $indicators['photo_details'];
            
            if ($photoDetails['recent_photo_count'] > 5) {
                $score += 20; // Lots of recent photos
            } elseif ($photoDetails['recent_photo_count'] > 0) {
                $score += 12; // Some recent photos
            }
            
            if ($photoDetails['unique_uploaders'] > 3) {
                $score += 15; // Active community
            } elseif ($photoDetails['unique_uploaders'] > 1) {
                $score += 8; // Some engagement
            }
            
            if ($photoDetails['newest_photo_age_days'] !== null && 
                $photoDetails['newest_photo_age_days'] < 14) {
                $score += 12; // Very recent photo
            }
        } elseif ($indicators['has_recent_photo']) {
            $score += 10; // Fallback if no details
        }
        
        // Online presence activity
        if ($indicators['has_social']) {
            $score += 10; // Has social media
        }
        
        if (isset($indicators['social_links']['website_age']) && 
            $indicators['social_links']['website_age'] !== null) {
            if ($indicators['social_links']['website_age'] === 0) {
                $score += 12; // Just launched website
            } elseif ($indicators['social_links']['website_age'] < 60) {
                $score += 6; // Recent website
            }
        } elseif ($indicators['has_website']) {
            $score += 5; // Has website (fallback)
        }

        return min(100, $score);
    }
    
    /**
     * Calculate penalties for negative indicators
     */
    private function calculatePenalties($indicators, $metadataAnalysis, $business): float
    {
        $penalty = 0;
        
        // Penalty for old business
        if ($metadataAnalysis['business_age_estimate'] === 'old') {
            $penalty += 40; // Strong penalty
        }
        
        // Penalty for rating decline
        if (isset($indicators['rating_decline']) && $indicators['rating_decline']) {
            $penalty += 15; // Getting worse
        }
        
        // Penalty for closed business
        $businessStatus = $indicators['business_status'] ?? 'OPERATIONAL';
        if ($businessStatus === 'CLOSED_PERMANENTLY') {
            $penalty += 80; // Essentially eliminates score
        } elseif ($businessStatus === 'CLOSED_TEMPORARILY') {
            $penalty += 25; // Moderate penalty
        }
        
        // Penalty for suspicious patterns
        if ($business->review_count > 500 && 
            in_array($metadataAnalysis['business_age_estimate'], ['ultra_new', 'very_new'])) {
            $penalty += 30; // Too many reviews for claimed age
        }
        
        return $penalty;
    }
    
    /**
     * Count positive indicators for combo bonus
     */
    private function countPositiveIndicators($indicators): int
    {
        $count = 0;
        
        if ($indicators['recently_opened']) $count++;
        if ($indicators['few_reviews'] || $indicators['low_rating_count']) $count++;
        if ($indicators['has_recent_photo']) $count++;
        if ($indicators['review_spike']) $count++;
        if ($indicators['is_new_operational']) $count++;
        if ($indicators['newly_discovered']) $count++;
        if ($indicators['has_social']) $count++;
        
        return $count;
    }

    public function updateMetadataForExistingData()
    {
        // Ambil semua bisnis yang belum memiliki metadata_analysis
        $businesses = Business::whereNull('indicators->metadata_analysis')
            ->orWhere('indicators->metadata_analysis', '{}')
            ->take(50) // Limit untuk batch processing
            ->get();

        $updated = 0;
        
        foreach ($businesses as $business) {
            try {
                // Simulasi metadata analysis berdasarkan data yang ada
                $indicators = $business->indicators ?? [];
                
                // Buat metadata analysis sederhana berdasarkan review_count dan first_seen
                $reviewCount = $business->review_count ?? 0;
                $firstSeen = $business->first_seen;
                $daysSinceFirstSeen = now()->diffInDays($firstSeen);
                
                $businessAgeEstimate = 'unknown';
                if ($daysSinceFirstSeen < 7) {
                    $businessAgeEstimate = 'ultra_new';
                } elseif ($daysSinceFirstSeen < 30) {
                    $businessAgeEstimate = 'very_new';
                } elseif ($daysSinceFirstSeen < 90) {
                    $businessAgeEstimate = 'new';
                } elseif ($daysSinceFirstSeen < 365) {
                    $businessAgeEstimate = 'recent';
                } elseif ($daysSinceFirstSeen < 1095) {
                    $businessAgeEstimate = 'established';
                } else {
                    $businessAgeEstimate = 'old';
                }
                
                $metadataAnalysis = [
                    'oldest_review_date' => $firstSeen->format('Y-m-d'),
                    'newest_review_date' => $firstSeen->format('Y-m-d'),
                    'review_age_months' => floor($daysSinceFirstSeen / 30),
                    'photo_count' => 0,
                    'has_recent_activity' => $daysSinceFirstSeen < 90,
                    'business_age_estimate' => $businessAgeEstimate,
                    'confidence_level' => 'medium',
                ];
                
                $indicators['metadata_analysis'] = $metadataAnalysis;
                $business->indicators = $indicators;
                $business->save();
                
                $updated++;
            } catch (\Exception $e) {
                // Log error jika ada
                Log::error("Error updating metadata for business {$business->id}: " . $e->getMessage());
            }
        }
        
        return response()->json([
            'message' => "Updated metadata for {$updated} businesses",
            'updated' => $updated,
            'total_processed' => $businesses->count()
        ]);
    }


    private function detectStatusChange($business, $currentStatus): array
    {
        $statusChange = [
            'is_new_operational' => false,
            'status_changed' => false,
            'previous_status' => null,
            'current_status' => $currentStatus,
            'status_age_days' => null,
        ];
        
        if (!$business->exists) {
            // New business
            $statusChange['is_new_operational'] = $currentStatus === 'OPERATIONAL';
            $statusChange['status_age_days'] = 0;
            return $statusChange;
        }
        
        // Get previous status from indicators if exists
        $previousStatus = $business->indicators['business_status'] ?? null;
        
        if ($previousStatus !== $currentStatus) {
            $statusChange['status_changed'] = true;
            $statusChange['previous_status'] = $previousStatus;
            
            // Check if became operational (was not operational before, now operational)
            if ($previousStatus !== 'OPERATIONAL' && $currentStatus === 'OPERATIONAL') {
                $statusChange['is_new_operational'] = true;
                $statusChange['status_age_days'] = 0;
            }
        } else {
            // Status same as before, calculate age
            if ($currentStatus === 'OPERATIONAL') {
                $statusChange['status_age_days'] = now()->diffInDays($business->first_seen);
            }
        }
        
        return $statusChange;
    }
    
    private function extractSocialLinks($info, $business): array
    {
        $socialLinks = [
            'website' => $info['website'] ?? null,
            'instagram' => null,
            'facebook' => null,
            'website_age' => null,
            'social_first_seen' => null,
            'has_website' => false,
            'has_social' => false,
        ];
        
        // Extract website
        if (!empty($info['website'])) {
            $socialLinks['has_website'] = true;
            
            // Track when first seen
            if ($business->exists && $business->website !== $info['website']) {
                $socialLinks['website_age'] = 0; // Just added/changed
            } elseif ($business->exists && $business->website === $info['website']) {
                // Calculate age from first_seen
                $socialLinks['website_age'] = now()->diffInDays($business->first_seen);
            }
        }
        
        // Try to extract social media from Google Places editorial summary or reviews
        if (isset($info['editorial_summary']['overview'])) {
            $overview = $info['editorial_summary']['overview'];
            
            // Instagram pattern
            if (preg_match('/@([a-zA-Z0-9._]+)/', $overview, $matches)) {
                $socialLinks['instagram'] = 'https://instagram.com/' . $matches[1];
                $socialLinks['has_social'] = true;
            }
            
            // Facebook pattern
            if (preg_match('/facebook\.com\/([a-zA-Z0-9.]+)/', $overview, $matches)) {
                $socialLinks['facebook'] = 'https://facebook.com/' . $matches[1];
                $socialLinks['has_social'] = true;
            }
        }
        
        // Check website URL for social media
        if (!empty($info['website'])) {
            $website = strtolower($info['website']);
            
            if (strpos($website, 'instagram.com') !== false) {
                $socialLinks['instagram'] = $info['website'];
                $socialLinks['has_social'] = true;
            }
            
            if (strpos($website, 'facebook.com') !== false) {
                $socialLinks['facebook'] = $info['website'];
                $socialLinks['has_social'] = true;
            }
        }
        
        return $socialLinks;
    }
    
    private function hasRecentPhoto(array $photos, int $daysThreshold = 90): array
    {
        // Brief requirement: foto <90 hari + unique uploaders
        if (empty($photos)) {
            return [
                'has_recent' => false,
                'recent_photo_count' => 0,
                'newest_photo_age_days' => null,
                'unique_uploaders' => 0,
                'total_photos' => 0
            ];
        }
        
        $recentPhotos = 0;
        $uploaders = [];
        $newestPhotoTime = 0;
        $thresholdTime = time() - ($daysThreshold * 24 * 60 * 60);
        
        foreach ($photos as $photo) {
            // Check if photo has time metadata
            if (isset($photo['time']) && $photo['time'] > $thresholdTime) {
                $recentPhotos++;
            }
            
            // Track newest photo
            if (isset($photo['time']) && $photo['time'] > $newestPhotoTime) {
                $newestPhotoTime = $photo['time'];
            }
            
            // Track unique uploaders/contributors
            if (isset($photo['author_name'])) {
                $uploaders[$photo['author_name']] = true;
            }
        }
        
        $photoAgeDays = $newestPhotoTime > 0 
            ? floor((time() - $newestPhotoTime) / (24 * 60 * 60)) 
            : null;
        
        return [
            'has_recent' => $recentPhotos > 0 || $photoAgeDays <= $daysThreshold,
            'recent_photo_count' => $recentPhotos,
            'newest_photo_age_days' => $photoAgeDays,
            'unique_uploaders' => count($uploaders),
            'total_photos' => count($photos)
        ];
    }

    /**
     * Validasi data bisnis sebelum disimpan
     */
    private function validateBusinessData($info, ?string $placeId = null): array
    {
        $errors = [];
        
        // Validasi field wajib
        if (empty($info['name'])) {
            $errors[] = 'Business name is required';
        }
        
        if (empty($info['formatted_address'])) {
            $errors[] = 'Address is required';
        }
        
        // Use provided place_id or check from info
        if (empty($placeId) && empty($info['place_id'])) {
            $errors[] = 'Place ID is required';
        }
        
        // Validasi koordinat
        if (!isset($info['geometry']['location']['lat']) || !isset($info['geometry']['location']['lng'])) {
            $errors[] = 'Valid coordinates are required';
        } else {
            $lat = $info['geometry']['location']['lat'];
            $lng = $info['geometry']['location']['lng'];
            
            if ($lat < -90 || $lat > 90) {
                $errors[] = 'Invalid latitude value';
            }
            
            if ($lng < -180 || $lng > 180) {
                $errors[] = 'Invalid longitude value';
            }
        }
        
        // Validasi rating jika ada
        if (isset($info['rating']) && ($info['rating'] < 1 || $info['rating'] > 5)) {
            $errors[] = 'Invalid rating value (must be 1-5)';
        }
        
        // Validasi review count jika ada
        if (isset($info['user_ratings_total']) && $info['user_ratings_total'] < 0) {
            $errors[] = 'Invalid review count (cannot be negative)';
        }
        
        // Validasi panjang nama bisnis
        if (strlen($info['name'] ?? '') > 255) {
            $errors[] = 'Business name is too long (max 255 characters)';
        }
        
        // Validasi panjang alamat
        if (strlen($info['formatted_address'] ?? '') > 1000) {
            $errors[] = 'Address is too long (max 1000 characters)';
        }
        
        return $errors;
    }

    /**
     * Cek duplikasi data berdasarkan kriteria tertentu
     */
    private function checkForDuplicates($info): ?Business
    {
        $name = $info['name'] ?? '';
        $address = $info['formatted_address'] ?? '';
        $lat = $info['geometry']['location']['lat'] ?? null;
        $lng = $info['geometry']['location']['lng'] ?? null;
        
        // Cek duplikasi berdasarkan nama yang sangat mirip (fuzzy matching)
        $existingBusiness = Business::where('name', 'LIKE', '%' . substr($name, 0, 20) . '%')
            ->first();
            
        if ($existingBusiness) {
            // Cek apakah alamat juga mirip
            $addressSimilarity = $this->calculateStringSimilarity($address, $existingBusiness->address);
            if ($addressSimilarity > 0.7) { // 70% similarity
                return $existingBusiness;
            }
        }
        
        // Cek duplikasi berdasarkan koordinat yang sangat dekat (dalam 50 meter)
        if ($lat && $lng) {
            $existingBusinesses = Business::whereNotNull('lat')
                ->whereNotNull('lng')
                ->get();
                
            foreach ($existingBusinesses as $business) {
                $distance = $this->calculateDistance($lat, $lng, $business->lat, $business->lng);
                if ($distance < 0.05) { // 50 meter = 0.05 km
                    return $business;
                }
            }
        }
        
        return null;
    }

    /**
     * Hitung jarak antara dua koordinat (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371; // Radius bumi dalam kilometer
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    /**
     * Hitung similarity antara dua string (Levenshtein distance)
     */
    private function calculateStringSimilarity($str1, $str2): float
    {
        if (empty($str1) || empty($str2)) {
            return 0;
        }
        
        $maxLength = max(strlen($str1), strlen($str2));
        $distance = levenshtein(strtolower($str1), strtolower($str2));
        
        return 1 - ($distance / $maxLength);
    }
}

