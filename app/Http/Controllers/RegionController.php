<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BaliRegion;
use App\Models\Business;
use App\Services\LocationParserService;
use Illuminate\Support\Facades\Log;

class RegionController extends Controller
{
    private LocationParserService $locationParser;

    public function __construct(LocationParserService $locationParser)
    {
        $this->locationParser = $locationParser;
    }
    /**
     * Get all Kabupaten
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getKabupaten()
    {
        try {
            $kabupaten = BaliRegion::kabupaten()
                ->orderBy('priority', 'asc')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'priority', 'center_lat', 'center_lng', 'search_radius'])
                ->unique('name') // Remove duplicate names (multiple zones for same kabupaten)
                ->values();
            
            // Group zones by kabupaten name for dropdown
            $grouped = $kabupaten->groupBy(function($item) {
                // Extract base kabupaten name (e.g., "Badung" from "Badung - Kuta & Seminyak")
                $parts = explode(' - ', $item->name);
                return $parts[0];
            })->map(function($items, $name) {
                return [
                    'id' => $items->first()->id,
                    'name' => $name,
                    'priority' => $items->first()->priority,
                    'zones_count' => $items->count()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $grouped
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch kabupaten: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch kabupaten',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Kecamatan by Kabupaten name
     * 
     * @param string $kabupatenName
     * @return \Illuminate\Http\JsonResponse
     */
    public function getKecamatan($kabupatenName)
    {
        try {
            // Get kecamatan from BaliRegion (from seeder)
            // Only get actual kecamatan, not kabupaten zones
            $kecamatan = BaliRegion::kecamatan()
                ->whereHas('parent', function($query) use ($kabupatenName) {
                    $query->where('name', 'LIKE', $kabupatenName . '%');
                })
                ->where('name', 'NOT LIKE', $kabupatenName . ' - %') // Exclude kabupaten zones
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'parent_id', 'center_lat', 'center_lng']);

            // Remove duplicates by name (in case there are multiple entries with same kecamatan name)
            $uniqueKecamatan = $kecamatan->unique('name')->values();

            Log::info('Kecamatan fetch', [
                'kabupaten' => $kabupatenName,
                'total_found' => $kecamatan->count(),
                'unique_count' => $uniqueKecamatan->count(),
                'duplicates_removed' => $kecamatan->count() - $uniqueKecamatan->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $uniqueKecamatan,
                'count' => $uniqueKecamatan->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch kecamatan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch kecamatan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get desa list for specific kabupaten and kecamatan
     * Parses desa names from business addresses dynamically
     * 
     * @param string $kabupaten
     * @param string $kecamatan
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDesa(string $kabupaten, string $kecamatan)
    {
        try {
            // Query businesses that match kabupaten and kecamatan
            $businesses = Business::whereNotNull('address')
                ->where(function($query) use ($kabupaten) {
                    $query->where('area', 'LIKE', "%{$kabupaten}%")
                          ->orWhere('address', 'LIKE', "%{$kabupaten}%");
                })
                ->where(function($query) use ($kecamatan) {
                    $query->where('address', 'LIKE', "%{$kecamatan}%");
                })
                ->get(['id', 'address', 'area']);

            // Parse desa from each business address
            $desaCounts = [];
            
            foreach ($businesses as $business) {
                $locationData = $this->locationParser->parseLocationHierarchy($business->address);
                
                // Only count if the parsed kabupaten and kecamatan match
                if ($locationData['kabupaten'] && $locationData['kecamatan'] && $locationData['desa']) {
                    $parsedKabupaten = $this->normalizeName($locationData['kabupaten']);
                    $parsedKecamatan = $this->normalizeName($locationData['kecamatan']);
                    $desa = $locationData['desa'];
                    
                    $targetKabupaten = $this->normalizeName($kabupaten);
                    $targetKecamatan = $this->normalizeName($kecamatan);
                    
                    // Check if parsed location matches the target
                    if ($parsedKabupaten === $targetKabupaten && $parsedKecamatan === $targetKecamatan) {
                        // Apply confidence filter - use 0% threshold for count calculation
                        // to show all desa that have data, regardless of confidence
                        $confidence = $business->indicators['new_business_confidence'] ?? 0;
                        $confidenceThreshold = 0; // Show all desa in dropdown, filter by confidence in main query
                        
                        if ($confidence >= $confidenceThreshold) {
                            if (!isset($desaCounts[$desa])) {
                                $desaCounts[$desa] = 0;
                            }
                            $desaCounts[$desa]++;
                        }
                    }
                }
            }

            // Convert to array format and sort, but only include desa with actual data
            $desaList = collect($desaCounts)
                ->filter(function($count, $name) {
                    // Only include desa that have actual business data
                    return $count > 0;
                })
                ->map(function($count, $name) {
                    return [
                        'name' => $name,
                        'count' => $count
                    ];
                })
                ->sortBy('name')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $desaList,
                'meta' => [
                    'kabupaten' => $kabupaten,
                    'kecamatan' => $kecamatan,
                    'total_businesses' => $businesses->count(),
                    'desa_count' => $desaList->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch desa: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch desa list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get full hierarchy (Kabupaten → Kecamatan → Desa)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHierarchy()
    {
        try {
            $kabupaten = BaliRegion::kabupaten()
                ->with(['children' => function($query) {
                    $query->kecamatan()->orderBy('name');
                }, 'children.children' => function($query) {
                    $query->desa()->orderBy('name');
                }])
                ->orderBy('priority')
                ->get();

            // Transform to clean hierarchy
            $hierarchy = $kabupaten->groupBy(function($item) {
                $parts = explode(' - ', $item->name);
                return $parts[0];
            })->map(function($items, $name) {
                $firstItem = $items->first();
                
                // Get unique kecamatan from all zones of this kabupaten
                $kecamatanCollection = collect();
                foreach($items as $item) {
                    $kecamatanCollection = $kecamatanCollection->merge($item->children);
                }
                
                return [
                    'id' => $firstItem->id,
                    'name' => $name,
                    'priority' => $firstItem->priority,
                    'zones_count' => $items->count(),
                    'kecamatan' => $kecamatanCollection->unique('id')->values()->map(function($kec) {
                        return [
                            'id' => $kec->id,
                            'name' => $kec->name,
                            'desa' => $kec->children->map(function($desa) {
                                return [
                                    'id' => $desa->id,
                                    'name' => $desa->name
                                ];
                            })
                        ];
                    })
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $hierarchy
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch hierarchy: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch hierarchy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug kecamatan data for specific kabupaten
     */
    public function debugKecamatan($kabupatenName)
    {
        try {
            // Get all data (including duplicates)
            $allKecamatan = BaliRegion::kecamatan()
                ->whereHas('parent', function($query) use ($kabupatenName) {
                    $query->where('name', 'LIKE', $kabupatenName . '%');
                })
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'parent_id', 'center_lat', 'center_lng']);

            // Get filtered data (excluding kabupaten zones)
            $filteredKecamatan = BaliRegion::kecamatan()
                ->whereHas('parent', function($query) use ($kabupatenName) {
                    $query->where('name', 'LIKE', $kabupatenName . '%');
                })
                ->where('name', 'NOT LIKE', $kabupatenName . ' - %')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'parent_id', 'center_lat', 'center_lng']);

            return response()->json([
                'success' => true,
                'kabupaten' => $kabupatenName,
                'all_kecamatan' => $allKecamatan,
                'filtered_kecamatan' => $filteredKecamatan,
                'all_count' => $allKecamatan->count(),
                'filtered_count' => $filteredKecamatan->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normalize name for comparison (remove common variations)
     */
    private function normalizeName(string $name): string
    {
        $name = trim($name);
        
        // Remove common prefixes
        $name = preg_replace('/^(Kabupaten|Kota|Kecamatan|Kec\.?)\s+/i', '', $name);
        
        // Convert to lowercase for comparison
        return strtolower($name);
    }
}

