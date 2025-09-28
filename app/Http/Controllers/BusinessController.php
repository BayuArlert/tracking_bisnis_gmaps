<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use Illuminate\Support\Facades\Http;

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

        // Apply pagination
        $skip = $request->get('skip', 0);
        $limit = $request->get('limit', 20);

        $businesses = $query->latest('first_seen')->skip($skip)->take($limit)->get();

        return response()->json([
            'data' => $businesses,
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

        // Clean and format areas
        $areas = [];
        foreach ($rawAreas as $area) {
            $cleanArea = $this->cleanAreaName($area);
            if (!in_array($cleanArea, $areas)) {
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
        
        // Handle specific cases
        if (strpos($clean, 'Bali') !== false) {
            return 'Bali';
        }
        
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
        
        return response()->json([
            'csv_data' => $csvData,
        ]);
    }

    public function fetchNew(Request $request)
    {
        $request->validate([
            'radius' => 'nullable|integer|min:100|max:50000',
        ]);

        $areas = [
            'Kabupaten Badung' => ['lat' => -8.650000, 'lng' => 115.216667],
            'Kabupaten Bangli' => ['lat' => -8.450000, 'lng' => 115.366667],
            'Kabupaten Buleleng' => ['lat' => -8.150000, 'lng' => 115.050000],
            'Kabupaten Gianyar' => ['lat' => -8.500000, 'lng' => 115.320000],
            'Kabupaten Jembrana' => ['lat' => -8.430000, 'lng' => 114.640000],
            'Kabupaten Karangasem' => ['lat' => -8.350000, 'lng' => 115.560000],
            'Kabupaten Klungkung' => ['lat' => -8.680000, 'lng' => 115.400000],
            'Kabupaten Tabanan' => ['lat' => -8.450000, 'lng' => 115.150000],
            'Kota Denpasar' => ['lat' => -8.650000, 'lng' => 115.216667],
        ];

        $radius = $request->radius ?? 3000;
        $key = config('services.gmaps.key');

        $newBusinesses = [];

        foreach ($areas as $areaName => $coords) {
            $lat = $coords['lat'];
            $lng = $coords['lng'];

            $url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json"
                . "?location={$lat},{$lng}&radius={$radius}&key={$key}";

            $places = Http::withOptions(['verify' => false])->get($url)->json();

            foreach ($places['results'] ?? [] as $place) {
                $detailUrl = "https://maps.googleapis.com/maps/api/place/details/json"
                    . "?place_id={$place['place_id']}&fields=name,rating,user_ratings_total,types,"
                    . "formatted_address,geometry,business_status,photos,reviews&key={$key}";

                $detail = Http::withOptions(['verify' => false])->get($detailUrl)->json();
                $info = $detail['result'] ?? null;
                if (!$info) continue;

                $business = Business::firstOrNew(['place_id' => $place['place_id']]);
                $isNew = false;

                if (!$business->exists) {
                    $business->first_seen = now();
                    $isNew = true;
                }

                $indicators = $this->generateBusinessIndicators($info, $business);

                $address = $info['formatted_address'] ?? '';

                $business->fill([
                    'name' => $info['name'],
                    'category' => $info['types'][0] ?? null, // kategori utama otomatis
                    'address' => $address,
                    'area' => $this->extractAreaFromAddress($address),
                    'lat' => $info['geometry']['location']['lat'] ?? null,
                    'lng' => $info['geometry']['location']['lng'] ?? null,
                    'rating' => $info['rating'] ?? null,
                    'review_count' => $info['user_ratings_total'] ?? 0,
                    'last_fetched' => now(),
                    'indicators' => $indicators,
                ]);
                $business->save();

                if ($isNew || $indicators['recently_opened']) {
                    $newBusinesses[] = $business;
                }
            }
        }

        return response()->json([
            'fetched' => count($newBusinesses),
            'new' => count($newBusinesses),
            'businesses' => $newBusinesses,
        ]);
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


    private function generateBusinessIndicators($info, $business)
    {
        $reviewCount = $info['user_ratings_total'] ?? 0;
        $rating = $info['rating'] ?? 0;
        $businessStatus = $info['business_status'] ?? '';
        $photos = $info['photos'] ?? [];
        $reviews = $info['reviews'] ?? [];
        
        // Analisis metadata untuk menentukan usia bisnis
        $metadataAnalysis = $this->analyzeBusinessMetadata($info, $reviews, $photos);
        
        // Logic untuk mendeteksi bisnis baru berdasarkan metadata
        $indicators = [
            'recently_opened' => $this->detectRecentlyOpenedFromMetadata($businessStatus, $metadataAnalysis),
            'few_reviews' => $reviewCount < 15,
            'low_rating_count' => $reviewCount < 5,
            'has_photos' => count($photos) > 0,
            'has_recent_photo' => $this->hasRecentPhoto($photos),
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

    private function detectRecentlyOpened($info, $business)
    {
        $businessStatus = $info['business_status'] ?? '';
        $reviewCount = $info['user_ratings_total'] ?? 0;
        
        // Google Maps API memberikan status "OPENED_RECENTLY" - ini yang paling akurat
        if ($businessStatus === 'OPENED_RECENTLY') {
            return true;
        }
        
        // Jika bisnis sudah ada di database dan first_seen > 30 hari, bukan bisnis baru
        if ($business->exists && $business->first_seen < now()->subDays(30)) {
            return false;
        }
        
        // Jika review count sangat rendah DAN rating ada, kemungkinan baru buka
        if ($reviewCount < 5 && $info['rating']) {
            return true;
        }
        
        // Jika review count rendah DAN tidak ada rating, kemungkinan baru buka
        if ($reviewCount < 3 && !$info['rating']) {
            return true;
        }
        
        return false;
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
        
        // Jika review count naik signifikan (lebih dari 50% dalam waktu singkat)
        $previousReviewCount = $business->review_count ?? 0;
        if ($previousReviewCount > 0) {
            $growth = (($currentReviewCount - $previousReviewCount) / $previousReviewCount) * 100;
            return $growth > 50;
        }
        
        return false;
    }

    private function isTrulyNewBusiness($info, $business)
    {
        $businessStatus = $info['business_status'] ?? '';
        $reviewCount = $info['user_ratings_total'] ?? 0;
        
        // Google Maps API memberikan status "OPENED_RECENTLY" - ini yang paling akurat
        if ($businessStatus === 'OPENED_RECENTLY') {
            return true;
        }
        
        // Jika bisnis sudah ada di database dengan first_seen lama, bukan bisnis baru
        if ($business->exists && $business->first_seen < now()->subDays(60)) {
            return false;
        }
        
        // Kriteria untuk bisnis benar-benar baru:
        // 1. Review count sangat rendah (< 3)
        // 2. Tidak ada rating atau rating rendah
        // 3. Tidak ada foto atau foto sedikit
        
        $hasLowActivity = $reviewCount < 3;
        $hasLowRating = !$info['rating'] || $info['rating'] < 3.0;
        $hasFewPhotos = count($info['photos'] ?? []) < 2;
        
        // Jika memenuhi minimal 2 dari 3 kriteria, kemungkinan bisnis baru
        $criteria = [$hasLowActivity, $hasLowRating, $hasFewPhotos];
        $metCriteria = array_filter($criteria);
        
        return count($metCriteria) >= 2;
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
        $score = 0;

        // Scoring berdasarkan metadata analysis
        switch ($metadataAnalysis['business_age_estimate']) {
            case 'ultra_new':
                $score += 60;
                break;
            case 'very_new':
                $score += 50;
                break;
            case 'new':
                $score += 35;
                break;
            case 'recent':
                $score += 20;
                break;
            case 'established':
                $score += 10;
                break;
            case 'old':
                $score += 0;
                break;
        }

        // Confidence level dari metadata
        switch ($metadataAnalysis['confidence_level']) {
            case 'high':
                $score += 20;
                break;
            case 'medium':
                $score += 10;
                break;
            case 'low':
                $score += 5;
                break;
        }

        // Faktor lain
        if ($indicators['recently_opened']) $score += 25;
        if ($indicators['few_reviews']) $score += 15;
        if ($indicators['low_rating_count']) $score += 20;
        if ($indicators['has_photos']) $score += 5;
        if ($indicators['has_recent_photo']) $score += 10;
        if ($indicators['rating_improvement']) $score += 10;
        if ($indicators['review_spike']) $score += 15;

        // Bonus untuk bisnis yang baru ditemukan
        if ($indicators['newly_discovered']) $score += 5;

        // Penalty untuk bisnis yang jelas sudah lama
        if ($metadataAnalysis['business_age_estimate'] === 'old') {
            $score = max(0, $score - 30);
        }

        return min(100, $score);
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
                \Log::error("Error updating metadata for business {$business->id}: " . $e->getMessage());
            }
        }
        
        return response()->json([
            'message' => "Updated metadata for {$updated} businesses",
            'updated' => $updated,
            'total_processed' => $businesses->count()
        ]);
    }

    private function calculateNewBusinessConfidence($indicators, $business)
    {
        $score = 0;
        
        // Scoring berdasarkan berbagai faktor
        if ($indicators['recently_opened']) $score += 35;
        if ($indicators['is_truly_new']) $score += 40; // Faktor terpenting
        if ($indicators['few_reviews']) $score += 15;
        if ($indicators['low_rating_count']) $score += 25;
        if ($indicators['has_photos']) $score += 5;
        if ($indicators['has_recent_photo']) $score += 10;
        if ($indicators['rating_improvement']) $score += 10;
        if ($indicators['review_spike']) $score += 15;
        
        // Bonus kecil untuk bisnis yang baru ditemukan (bukan bisnis baru)
        if ($indicators['newly_discovered']) $score += 5;
        
        return min(100, $score); // Maksimal 100
    }

    private function hasRecentPhoto(array $photos): bool
    {
        // Untuk saat ini, kita anggap bisnis yang punya foto adalah yang lebih aktif
        // Di masa depan bisa diintegrasikan dengan Google Photos API untuk cek tanggal
        return count($photos) > 0;
    }
}
