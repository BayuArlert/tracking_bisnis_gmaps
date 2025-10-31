<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    /**
     * Apply area filter that handles cleaned area names
     * This matches areas like "Bali" against "Bali 82181", "Bali 82152", etc.
     */
    private function applyAreaFilter($query, $area)
    {
        if ($area === 'all') {
            return $query;
        }

        // Get all original area names that match the cleaned area
        $originalAreas = $this->getOriginalAreaNames($area);
        return $query->whereIn('area', $originalAreas);
    }

    /**
     * Get original area names that match the cleaned area name
     * Similar to BusinessController logic
     */
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

    /**
     * Clean area name by removing numeric codes
     * Based on ACTUAL DATA in database - Same logic as BusinessController
     */
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
        
        // Explicitly check for all Bali regencies/cities FIRST
        if (stripos($clean, 'Kabupaten Tabanan') !== false) return 'Kabupaten Tabanan';
        if (stripos($clean, 'Kabupaten Bangli') !== false) return 'Kabupaten Bangli';
        if (stripos($clean, 'Kabupaten Buleleng') !== false) return 'Kabupaten Buleleng';
        if (stripos($clean, 'Kabupaten Gianyar') !== false) return 'Kabupaten Gianyar';
        if (stripos($clean, 'Kabupaten Karangasem') !== false) return 'Kabupaten Karangasem';
        if (stripos($clean, 'Kabupaten Klungkung') !== false) return 'Kabupaten Klungkung';
        if (stripos($clean, 'Kota Denpasar') !== false) return 'Kota Denpasar';
        
        // If it's clearly not Bali, return null to filter out
        $nonBaliAreas = [
            'Jawa Timur', 'Jakarta', 'Surabaya', 'Bandung', 'Yogyakarta', 
            'Solo', 'Semarang', 'Malang', 'Medan', 'Palembang',
            'Makassar', 'Manado', 'Pontianak', 'Balikpapan',
            'Lombok', 'Flores', 'Sumba', 'Timor', 'Papua',
            'Kalimantan', 'Sumatra', 'Sulawesi', 'Nusa Tenggara',
            'West Java', 'Kota Bandung', 'Kota Semarang',
            'Kabupaten Jember', 'Kabupaten Sayan', 'Kabupaten Sigi'
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
    public function index(Request $request)
    {
        $period = $request->get('period', 'weekly');
        $category = $request->get('category', 'all');
        $area = $request->get('area', 'all');
        $kabupaten = $request->get('kabupaten', '');
        $kecamatan = $request->get('kecamatan', '');
        $minConfidence = $request->get('min_confidence', 0);

        // Base query
        $query = Business::query();

        // Apply hierarchical location filters (PRIORITY) - Same logic as BusinessController
        if (!empty($kabupaten)) {
            $query->where(function($q) use ($kabupaten) {
                $kab = strtolower($kabupaten);
                $q
                  // Area field exact forms
                  ->whereRaw('LOWER(area) LIKE ?', ['%kabupaten ' . $kab . '%'])
                  ->orWhereRaw('LOWER(area) LIKE ?', ['%kota ' . $kab . '%'])
                  // Address field - comma delimited segment or formal forms
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%, kabupaten ' . $kab . ',%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%, kota ' . $kab . ',%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['% ' . $kab . ' regency%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%, ' . $kab . ', bali%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%, ' . $kab . ', indonesia%']);
            });
        }

        if (!empty($kecamatan)) {
            $query->where(function($q) use ($kecamatan) {
                // Try different formats: "Kecamatan X", "Kec. X", "X" (case insensitive)
                $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kecamatan ' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ',%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ' %']);
            });
        }

        // Add desa filter - More precise matching
        $desa = $request->get('desa', '');
        if (!empty($desa)) {
            $query->where(function($q) use ($desa) {
                // Pattern 1: ", Desa, Kec." - desa appears before kecamatan
                $q->whereRaw('LOWER(address) LIKE ?', ['%, ' . strtolower($desa) . ', %kec.%'])
                  // Pattern 2: ", Desa, Kecamatan" - full word kecamatan
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%, ' . strtolower($desa) . ', %kecamatan%'])
                  // Pattern 3: End of address segment (followed by comma and capital letter)
                  ->orWhereRaw('LOWER(address) REGEXP ?', [', ' . strtolower($desa) . ', [A-Z]']);
            });
        }

        // Apply category filter (support multi-select)
        if ($category !== 'all' && !empty($category)) {
            $categories = explode(',', $category);
            $query->where(function($q) use ($categories) {
                foreach ($categories as $cat) {
                    $q->orWhere('category', 'like', '%' . trim($cat) . '%');
                }
            });
        }

        // Apply area filter (fallback if no hierarchical filters)
        if (empty($kabupaten) && empty($kecamatan)) {
            $query = $this->applyAreaFilter($query, $area);
        }

        // Apply confidence threshold filter
        if ($minConfidence > 0) {
            $query->whereRaw('JSON_EXTRACT(indicators, "$.new_business_confidence") >= ?', [$minConfidence / 100]);
        }

        // Get basic stats - Use filtered query with caching
        $cacheKey = 'stats_' . md5(serialize($request->all()));
        $stats = Cache::remember($cacheKey, 300, function() use ($query) {
            return [
                'total' => (clone $query)->count(),
                'new_week' => (clone $query)->where('first_seen', '>=', now()->subWeek())->count(),
                'new_month' => (clone $query)->where('first_seen', '>=', now()->subMonth())->count(),
            ];
        });
        
        $totalBusinesses = $stats['total'];
        $newThisWeek = $stats['new_week'];
        $newThisMonth = $stats['new_month'];
        
        // Calculate growth rate - Use filtered query
        $previousWeek = (clone $query)->whereBetween('first_seen', [now()->subWeeks(2), now()->subWeek()])->count();
        $growthRate = $previousWeek > 0 ? (($newThisWeek - $previousWeek) / $previousWeek) * 100 : 0;

        // Get trend data
        $weeklyTrends = $this->getWeeklyTrends($query);
        $monthlyTrends = $this->getMonthlyTrends($query);
        $categoryTrends = $this->getCategoryTrends($query);
        $areaTrends = $this->getAreaTrends($query);

        // Get top businesses
        $topBusinesses = $this->getTopBusinesses($query);

        return response()->json([
            'total_businesses' => $totalBusinesses,
            'new_this_week' => $newThisWeek,
            'new_this_month' => $newThisMonth,
            'growth_rate' => round($growthRate, 1),
            'weekly_trends' => $weeklyTrends,
            'monthly_trends' => $monthlyTrends,
            'category_trends' => $categoryTrends,
            'area_trends' => $areaTrends,
            'top_businesses' => $topBusinesses,
        ]);
    }

    private function getWeeklyTrends($query)
    {
        $trends = [];
        
        // Get last 8 weeks
        for ($i = 7; $i >= 0; $i--) {
            $startDate = now()->subWeeks($i)->startOfWeek();
            $endDate = now()->subWeeks($i)->endOfWeek();
            
            $count = (clone $query)->whereBetween('first_seen', [$startDate, $endDate])->count();
            
            $trends[] = [
                'period' => $startDate->format('M d'),
                'count' => $count,
            ];
        }

        return $trends;
    }

    private function getMonthlyTrends($query)
    {
        $trends = [];
        
        // Get last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();
            
            $count = (clone $query)->whereBetween('first_seen', [$startDate, $endDate])->count();
            
            $trends[] = [
                'period' => $startDate->format('M Y'),
                'count' => $count,
            ];
        }

        return $trends;
    }

    private function getCategoryTrends($query)
    {
        $categories = (clone $query)
            ->select('category', DB::raw('COUNT(*) as count'))
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return $categories->map(function ($item) {
            return [
                'period' => $this->cleanCategoryName($item->category),
                'count' => $item->count,
                'category' => $item->category,
            ];
        })->toArray();
    }

    private function getAreaTrends($query)
    {
        $areas = (clone $query)
            ->select('area', DB::raw('COUNT(*) as count'))
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->groupBy('area')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return $areas->map(function ($item) {
            return [
                'period' => $this->cleanAreaName($item->area),
                'count' => $item->count,
                'area' => $item->area,
            ];
        })->toArray();
    }

    private function getTopBusinesses($query)
    {
        return (clone $query)
            ->where('first_seen', '>=', now()->subMonth())
            ->orderBy('review_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'category' => $business->category,
                    'area' => $business->area,
                    'rating' => $business->rating,
                    'review_count' => $business->review_count,
                    'first_seen' => $business->first_seen,
                    'indicators' => $business->indicators,
                ];
            })
            ->toArray();
    }

    private function cleanCategoryName($category)
    {
        // Convert snake_case to Title Case
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


    public function getHeatmapData(Request $request)
    {
        $category = $request->get('category', 'all');
        $area = $request->get('area', 'all');
        $period = $request->get('period', 'all');
        $minConfidence = $request->get('min_confidence', 0);
        $kabupaten = $request->get('kabupaten', '');
        $kecamatan = $request->get('kecamatan', '');

        $query = Business::query();

        // Apply hierarchical location filters (PRIORITY) - Same logic as BusinessController
        if (!empty($kabupaten)) {
            $query->where(function($q) use ($kabupaten) {
                // Try different formats: "Kabupaten X", "Kota X", "X" (case insensitive)
                $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kabupaten ' . strtolower($kabupaten) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kota ' . strtolower($kabupaten) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ',%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kabupaten) . ' %']);
            });
        }

        if (!empty($kecamatan)) {
            $query->where(function($q) use ($kecamatan) {
                // Try different formats: "Kecamatan X", "Kec. X", "X" (case insensitive)
                $q->whereRaw('LOWER(area) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kecamatan ' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%kec. ' . strtolower($kecamatan) . '%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ',%'])
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%' . strtolower($kecamatan) . ' %']);
            });
        }

        // Add desa filter - More precise matching
        $desa = $request->get('desa', '');
        if (!empty($desa)) {
            $query->where(function($q) use ($desa) {
                // Pattern 1: ", Desa, Kec." - desa appears before kecamatan
                $q->whereRaw('LOWER(address) LIKE ?', ['%, ' . strtolower($desa) . ', %kec.%'])
                  // Pattern 2: ", Desa, Kecamatan" - full word kecamatan
                  ->orWhereRaw('LOWER(address) LIKE ?', ['%, ' . strtolower($desa) . ', %kecamatan%'])
                  // Pattern 3: End of address segment (followed by comma and capital letter)
                  ->orWhereRaw('LOWER(address) REGEXP ?', [', ' . strtolower($desa) . ', [A-Z]']);
            });
        }

        // Apply category filter (support multi-select with comma separated)
        if ($category !== 'all' && !empty($category)) {
            $categories = explode(',', $category);
            $query->where(function($q) use ($categories) {
                foreach ($categories as $cat) {
                    $q->orWhere('category', 'like', '%' . trim($cat) . '%');
                }
            });
        }

        // Apply area filter (fallback if no hierarchical filters)
        if (empty($kabupaten) && empty($kecamatan)) {
            $query = $this->applyAreaFilter($query, $area);
        }

        // Apply confidence threshold filter
        if ($minConfidence > 0) {
            $query->whereRaw('JSON_EXTRACT(indicators, "$.new_business_confidence") >= ?', [$minConfidence / 100]);
        }

        // Apply time filter (only if period is not 'all')
        // Support both named periods and day counts (30, 60, 90, 180)
        if ($period === 'weekly' || $period === '7') {
            $query->where('first_seen', '>=', now()->subDays(7));
        } elseif ($period === 'monthly' || $period === '30') {
            $query->where('first_seen', '>=', now()->subDays(30));
        } elseif ($period === '60') {
            $query->where('first_seen', '>=', now()->subDays(60));
        } elseif ($period === 'quarterly' || $period === '90') {
            $query->where('first_seen', '>=', now()->subDays(90));
        } elseif ($period === '180') {
            $query->where('first_seen', '>=', now()->subDays(180));
        } elseif ($period === 'yearly' || $period === '365') {
            $query->where('first_seen', '>=', now()->subDays(365));
        }
        // If period === 'all', no time filter applied

        $businesses = $query->whereNotNull('lat')
            ->whereNotNull('lng')
            ->select('lat', 'lng', 'name', 'category', 'area', 'review_count', 'rating')
            ->get();

        return response()->json([
            'period' => $period,
            'total_businesses' => $businesses->count(),
            'businesses' => $businesses->map(function ($business) {
                return [
                    'lat' => is_numeric($business->lat) ? (float) $business->lat : 0,
                    'lng' => is_numeric($business->lng) ? (float) $business->lng : 0,
                    'name' => $business->name,
                    'category' => $business->category,
                    'area' => $business->area,
                    'review_count' => (int) $business->review_count,
                    'rating' => $business->rating ? (float) $business->rating : null,
                ];
            }),
        ]);
    }

    /**
     * Get hot zones (kecamatan with most new businesses)
     */
    public function hotZones(Request $request)
    {
        $period = (int) $request->get('period', 90);
        $category = $request->get('category', 'all');
        $limit = (int) $request->get('limit', 5);
        
        $dateFrom = now()->subDays($period);
        
        $query = Business::where('first_seen', '>=', $dateFrom);
        
        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }
        
        // Extract kecamatan from area field and group
        $businesses = $query->get();
        
        $zones = [];
        foreach ($businesses as $business) {
            $area = $business->area ?? '';
            
            // Extract kecamatan from area string (format: "Kecamatan, Kabupaten, Bali")
            $parts = array_map('trim', explode(',', $area));
            
            $kecamatan = 'Unknown';
            $kabupaten = 'Unknown';
            
            // Try to identify kecamatan and kabupaten from parts
            foreach ($parts as $part) {
                if (strpos($part, 'Kabupaten') !== false || strpos($part, 'Kota') !== false) {
                    $kabupaten = $part;
                } elseif (strpos($part, 'Kec.') !== false) {
                    $kecamatan = str_replace('Kec. ', '', $part);
                } elseif (strpos($part, 'Bali') === false && strpos($part, 'Indonesia') === false) {
                    // Assume it's kecamatan if not Bali/Indonesia
                    if ($kecamatan === 'Unknown') {
                        $kecamatan = $part;
                    }
                }
            }
            
            $key = "{$kabupaten}|{$kecamatan}";
            
            if (!isset($zones[$key])) {
                $zones[$key] = [
                    'kabupaten' => $kabupaten,
                    'kecamatan' => $kecamatan,
                    'area' => $kecamatan . ', ' . $kabupaten,
                    'count' => 0,
                    'category_breakdown' => [],
                ];
            }
            
            $zones[$key]['count']++;
            
            $cat = $business->category ?? 'Unknown';
            if (!isset($zones[$key]['category_breakdown'][$cat])) {
                $zones[$key]['category_breakdown'][$cat] = 0;
            }
            $zones[$key]['category_breakdown'][$cat]++;
        }
        
        // Convert to array and sort by count
        $zones = array_values($zones);
        usort($zones, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Take top N
        $topZones = array_slice($zones, 0, $limit);
        
        // Calculate growth percentage (simplified - comparing to total average)
        $averageCount = count($zones) > 0 ? array_sum(array_column($zones, 'count')) / count($zones) : 0;
        
        foreach ($topZones as &$zone) {
            if ($averageCount > 0) {
                $zone['growth_percentage'] = round((($zone['count'] - $averageCount) / $averageCount) * 100, 1);
            } else {
                $zone['growth_percentage'] = 0;
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $topZones,
            'metadata' => [
                'period' => $period,
                'category' => $category,
                'total_zones' => count($zones),
                'average_per_zone' => round($averageCount, 1)
            ]
        ]);
    }
}

