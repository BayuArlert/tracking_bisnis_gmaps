<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExportController extends Controller
{
    /**
     * Test CSV endpoint - simple response
     */
    public function testCsv()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="test.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Name', 'Category', 'Area']);
            fputcsv($file, ['Test Business', 'Café', 'Badung']);
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
    /**
     * Export businesses to CSV
     */
    public function csv(Request $request)
    {
        try {
            Log::info('CSV Export Request', [
                'filters' => $request->all(),
                'user_agent' => $request->userAgent(),
                'accept' => $request->header('Accept'),
            ]);
            
        $businesses = $this->getFilteredBusinesses($request);

            Log::info('CSV Export Data', [
                'business_count' => $businesses->count(),
                'first_business' => $businesses->first() ? $businesses->first()->toArray() : null,
            ]);

        $filename = 'businesses_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
        ];

        $callback = function() use ($businesses) {
            $file = fopen('php://output', 'w');
                
                // Add BOM for UTF-8 compatibility with Excel
                fwrite($file, "\xEF\xBB\xBF");
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Name', 'Category', 'Types', 'Area', 'Address', 'Phone', 'Website',
                'Rating', 'Review Count', 'First Seen', 'Last Fetched', 'Latitude', 'Longitude',
                'Google Maps URL', 'Recently Opened', 'New Business Confidence', 'Business Age Estimate',
                'Has Photos', 'Has Recent Photo', 'Review Burst', 'Rating Improvement'
                ], ',');

            foreach ($businesses as $business) {
                $indicators = $business->indicators ?? [];
                $metadata = $indicators['metadata_analysis'] ?? [];
                
                fputcsv($file, [
                        $business->id ?? '',
                        $business->name ?? '',
                        $business->category ?? '',
                    implode(';', $business->types ?? []),
                        $business->area ?? '',
                        $business->address ?? '',
                        $business->phone ?? '',
                        $business->website ?? '',
                        $business->rating ?? '',
                        $business->review_count ?? '',
                        $business->first_seen?->format('Y-m-d H:i:s') ?? '',
                        $business->last_fetched?->format('Y-m-d H:i:s') ?? '',
                        $business->lat ?? '',
                        $business->lng ?? '',
                        $business->google_maps_url ?? '',
                    $indicators['recently_opened'] ? 'Yes' : 'No',
                    $indicators['new_business_confidence'] ?? 0,
                    $metadata['business_age_estimate'] ?? 'unknown',
                    $indicators['has_photos'] ? 'Yes' : 'No',
                    $indicators['has_recent_photo'] ? 'Yes' : 'No',
                    $indicators['review_spike'] ? 'Yes' : 'No',
                    $indicators['rating_improvement'] ? 'Yes' : 'No',
                    ], ',');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
            
        } catch (\Exception $e) {
            Log::error('CSV Export Error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Export failed',
                'message' => 'Unable to generate CSV file'
            ], 500);
        }
    }

    /**
     * Export businesses to JSON
     */
    public function json(Request $request)
    {
        $businesses = $this->getFilteredBusinesses($request);

        $filename = 'businesses_export_' . date('Y-m-d_H-i-s') . '.json';
        
        $data = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_businesses' => $businesses->count(),
                'filters_applied' => $request->all(),
            ],
            'businesses' => $businesses->map(function ($business) {
                return [
                    'id' => $business->id,
                    'place_id' => $business->place_id,
                    'name' => $business->name,
                    'category' => $business->category,
                    'types' => $business->types,
                    'area' => $business->area,
                    'address' => $business->address,
                    'phone' => $business->phone,
                    'website' => $business->website,
                    'coordinates' => [
                        'lat' => $business->lat,
                        'lng' => $business->lng,
                    ],
                    'rating' => $business->rating,
                    'review_count' => $business->review_count,
                    'timestamps' => [
                        'first_seen' => $business->first_seen?->toISOString(),
                        'last_fetched' => $business->last_fetched?->toISOString(),
                    ],
                    'google_maps_url' => $business->google_maps_url,
                    'indicators' => $business->indicators,
                    'opening_hours' => $business->opening_hours,
                    'price_level' => $business->price_level,
                ];
            }),
        ];

        return Response::json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export map image using Google Static Maps API
     */
    public function mapImage(Request $request)
    {
        try {
            $businesses = $this->getFilteredBusinesses($request);
            
            if ($businesses->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No businesses to display on map'
                ], 400);
            }
            
            // Calculate center point (average of all business coordinates)
            $centerLat = $businesses->avg('lat');
            $centerLng = $businesses->avg('lng');
            
            // Determine zoom level based on spread
            $latRange = $businesses->max('lat') - $businesses->min('lat');
            $lngRange = $businesses->max('lng') - $businesses->min('lng');
            $maxRange = max($latRange, $lngRange);
            
            // Auto zoom calculation
            $zoom = 12; // Default
            if ($maxRange > 0.5) $zoom = 9;
            elseif ($maxRange > 0.2) $zoom = 10;
            elseif ($maxRange > 0.1) $zoom = 11;
            elseif ($maxRange > 0.05) $zoom = 12;
            else $zoom = 13;
            
            $apiKey = config('services.gmaps.key');
            
            // Build Static Maps URL
            $baseUrl = 'https://maps.googleapis.com/maps/api/staticmap?';
            $params = [
                'center' => "{$centerLat},{$centerLng}",
                'zoom' => $zoom,
                'size' => '800x600',
                'maptype' => 'roadmap',
                'key' => $apiKey
            ];
            
            // Add markers (max 100 to avoid URL length limit)
            $markerCount = 0;
            $maxMarkers = 100;
            foreach ($businesses->take($maxMarkers) as $business) {
                if ($business->lat && $business->lng) {
                    // Color markers by confidence
                    $confidence = $business->indicators['new_business_confidence'] ?? 0;
                    $color = 'red'; // Default
                    if ($confidence >= 80) $color = 'green';
                    elseif ($confidence >= 60) $color = 'blue';
                    elseif ($confidence >= 40) $color = 'orange';
                    
                    $params["markers{$markerCount}"] = "color:{$color}|size:small|{$business->lat},{$business->lng}";
                    $markerCount++;
                }
            }
            
            // Build URL
            $staticMapUrl = $baseUrl . http_build_query($params);
            
            // If markers exceed limit, add note
            $note = null;
            if ($businesses->count() > $maxMarkers) {
                $note = "Showing {$maxMarkers} of {$businesses->count()} businesses. To see all, adjust filters.";
            }
        
        return response()->json([
                'success' => true,
                'image_url' => $staticMapUrl,
                'metadata' => [
                    'total_businesses' => $businesses->count(),
                    'markers_shown' => $markerCount,
                    'center' => ['lat' => $centerLat, 'lng' => $centerLng],
                    'zoom' => $zoom,
                    'note' => $note
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate map image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filtered businesses based on request parameters
     */
    private function getFilteredBusinesses(Request $request)
    {
        $query = Business::query();

        // Area filter
        if ($request->has('area') && $request->area !== 'all' && $request->area !== '') {
            $query->where('area', 'LIKE', '%' . $request->area . '%');
        }

        // Hierarchical location filters (Kabupaten & Kecamatan)
        if ($request->has('kabupaten') && $request->kabupaten) {
            $query->where('area', 'LIKE', '%' . $request->kabupaten . '%');
        }

        if ($request->has('kecamatan') && $request->kecamatan) {
            $query->where('area', 'LIKE', '%' . $request->kecamatan . '%');
        }

        // Category filter
        if ($request->has('category') && $request->category !== 'all' && $request->category !== '') {
            $query->where('category', $request->category);
        }

        // Multiple categories filter
        if ($request->has('categories') && is_array($request->categories)) {
            $query->whereIn('category', $request->categories);
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->where('first_seen', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('first_seen', '<=', $request->date_to);
        }

        // Period filter (last N days)
        if ($request->has('period')) {
            $period = (int) $request->period;
            $query->where('first_seen', '>=', now()->subDays($period));
        }

        // New business confidence filter
        if ($request->has('min_confidence')) {
            $minConfidence = (int) $request->min_confidence;
            $query->whereRaw('JSON_EXTRACT(indicators, "$.new_business_confidence") >= ?', [$minConfidence]);
        }

        // Business age filter
        if ($request->has('business_age')) {
            $age = $request->business_age;
            $query->whereRaw('JSON_EXTRACT(indicators, "$.metadata_analysis.business_age_estimate") = ?', [$age]);
        }

        // Recently opened filter
        if ($request->has('recently_opened') && $request->recently_opened) {
            $query->whereJsonContains('indicators->recently_opened', true);
        }

        // Radius filter
        if ($request->has('use_radius') && $request->use_radius == 'true' 
            && $request->has('radius') && $request->has('center_lat') && $request->has('center_lng')) {
            
            $centerLat = (float) $request->center_lat;
            $centerLng = (float) $request->center_lng;
            $radius = (int) $request->radius;

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

        // For export, get ALL results (no limit)
        // Only apply limit if explicitly requested and reasonable
        $limit = $request->get('limit');
        if ($limit && $limit > 0 && $limit <= 50000) {
            $query->limit($limit);
        }
        // If no limit specified or limit > 50000, export all results

        $businesses = $query->orderBy('first_seen', 'desc')->get();
        
        Log::info('Export Query Results', [
            'total_count' => $businesses->count(),
            'filters_applied' => $request->all(),
            'query_sql' => $query->toSql(),
        ]);

        return $businesses;
    }

    /**
     * Get export options and available filters
     */
    public function options()
    {
        return response()->json([
            'formats' => ['csv', 'json', 'map_image'],
            'filters' => [
                'area' => [
                    'type' => 'string',
                    'description' => 'Filter by area name',
                    'options' => Business::select('area')->distinct()->whereNotNull('area')->pluck('area'),
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Filter by single category',
                    'options' => Business::select('category')->distinct()->whereNotNull('category')->pluck('category'),
                ],
                'categories' => [
                    'type' => 'array',
                    'description' => 'Filter by multiple categories',
                    'options' => Business::select('category')->distinct()->whereNotNull('category')->pluck('category'),
                ],
                'date_from' => [
                    'type' => 'date',
                    'description' => 'Filter businesses from this date',
                ],
                'date_to' => [
                    'type' => 'date',
                    'description' => 'Filter businesses until this date',
                ],
                'period' => [
                    'type' => 'integer',
                    'description' => 'Filter businesses from last N days',
                ],
                'min_confidence' => [
                    'type' => 'integer',
                    'description' => 'Minimum new business confidence score (0-100)',
                    'min' => 0,
                    'max' => 100,
                ],
                'business_age' => [
                    'type' => 'string',
                    'description' => 'Filter by business age estimate',
                    'options' => ['ultra_new', 'very_new', 'new', 'recent', 'established', 'old'],
                ],
                'recently_opened' => [
                    'type' => 'boolean',
                    'description' => 'Filter only recently opened businesses',
                ],
                'use_radius' => [
                    'type' => 'boolean',
                    'description' => 'Enable radius-based filtering',
                ],
                'center_lat' => [
                    'type' => 'float',
                    'description' => 'Center latitude for radius filter',
                ],
                'center_lng' => [
                    'type' => 'float',
                    'description' => 'Center longitude for radius filter',
                ],
                'radius' => [
                    'type' => 'integer',
                    'description' => 'Radius in meters for radius filter',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results (0 = no limit)',
                    'default' => 1000,
                ],
            ],
        ]);
    }
}
