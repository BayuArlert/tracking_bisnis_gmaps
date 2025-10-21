<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use App\Models\BusinessSnapshot;
use App\Models\ScrapeSession;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Clean area name by removing numeric codes
     * Same logic as BusinessController and StatisticsController
     */
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
     * Get trend data (weekly/monthly)
     */
    public function trends(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:weekly,monthly',
            'months' => 'nullable|integer|min:1|max:12',
        ]);

        $period = $request->get('period', 'weekly');
        $months = $request->get('months', 6);

        $startDate = now()->subMonths($months);

        if ($period === 'weekly') {
            $trends = $this->getWeeklyTrends($startDate);
        } else {
            $trends = $this->getMonthlyTrends($startDate);
        }

        return response()->json([
            'period' => $period,
            'data' => $trends,
        ]);
    }

    /**
     * Get hot zones (top growth areas)
     */
    public function hotZones(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
            'period' => 'nullable|in:7,30,90',
        ]);

        $limit = $request->get('limit', 10);
        $period = $request->get('period', 30);

        $startDate = now()->subDays($period);

        $hotZones = Business::select([
                'area',
                DB::raw('COUNT(*) as total_businesses'),
                DB::raw('SUM(CASE WHEN first_seen >= ? THEN 1 ELSE 0 END) as new_businesses'),
                DB::raw('AVG(JSON_EXTRACT(indicators, "$.new_business_confidence")) as avg_confidence'),
                DB::raw('AVG(lat) as avg_lat'),
                DB::raw('AVG(lng) as avg_lng'),
            ])
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->where('first_seen', '>=', $startDate)
            ->groupBy('area')
            ->having('new_businesses', '>', 0)
            ->orderBy('new_businesses', 'desc')
            ->limit($limit)
            ->setBindings([$startDate])
            ->get();

        return response()->json([
            'period_days' => $period,
            'hot_zones' => $hotZones,
        ]);
    }

    /**
     * Get summary metrics
     */
    public function summary(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:7,30,90',
        ]);

        $period = $request->get('period', 30);
        $startDate = now()->subDays($period);

        $summary = [
            'total_businesses' => Business::count(),
            'new_businesses' => Business::where('first_seen', '>=', $startDate)->count(),
            'recently_opened' => Business::whereJsonContains('indicators->recently_opened', true)
                ->where('first_seen', '>=', $startDate)
                ->count(),
            'high_confidence_new' => Business::where('first_seen', '>=', $startDate)
                ->whereRaw('JSON_EXTRACT(indicators, "$.new_business_confidence") > 60')
                ->count(),
            'categories_breakdown' => $this->getCategoriesBreakdown($startDate),
            'areas_breakdown' => $this->getAreasBreakdown($startDate),
            'growth_rate' => $this->calculateGrowthRate($period),
            'api_usage' => $this->getApiUsageSummary($startDate),
        ];

        return response()->json($summary);
    }

    /**
     * Get category breakdown
     */
    public function categoryBreakdown(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:7,30,90',
        ]);

        $period = $request->get('period', 30);
        $startDate = now()->subDays($period);

        $breakdown = $this->getCategoriesBreakdown($startDate);

        return response()->json([
            'period_days' => $period,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Get area breakdown
     */
    public function areaBreakdown(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:7,30,90',
        ]);

        $period = $request->get('period', 30);
        $startDate = now()->subDays($period);

        $breakdown = $this->getAreasBreakdown($startDate);

        return response()->json([
            'period_days' => $period,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Get business age distribution
     */
    public function ageDistribution()
    {
        $distribution = Business::selectRaw('
                JSON_EXTRACT(indicators, "$.metadata_analysis.business_age_estimate") as age_estimate,
                COUNT(*) as count
            ')
            ->whereNotNull('indicators')
            ->groupBy('age_estimate')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->age_estimate => $item->count];
            });

        return response()->json([
            'distribution' => $distribution,
        ]);
    }

    /**
     * Get confidence score distribution
     */
    public function confidenceDistribution()
    {
        $distribution = Business::selectRaw('
                CASE 
                    WHEN JSON_EXTRACT(indicators, "$.new_business_confidence") >= 80 THEN "80-100"
                    WHEN JSON_EXTRACT(indicators, "$.new_business_confidence") >= 60 THEN "60-79"
                    WHEN JSON_EXTRACT(indicators, "$.new_business_confidence") >= 40 THEN "40-59"
                    WHEN JSON_EXTRACT(indicators, "$.new_business_confidence") >= 20 THEN "20-39"
                    ELSE "0-19"
                END as confidence_range,
                COUNT(*) as count
            ')
            ->whereNotNull('indicators')
            ->groupBy('confidence_range')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->confidence_range => $item->count];
            });

        return response()->json([
            'distribution' => $distribution,
        ]);
    }

    /**
     * Get trends per category (multi-line chart data)
     * Sesuai Brief: Tren mingguan penambahan PER KATEGORI
     */
    public function trendsPerCategory(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:weekly,monthly',
            'weeks' => 'nullable|integer|min:1|max:52',
        ]);

        $period = $request->get('period', 'weekly');
        $weeks = $request->get('weeks', 12);
        
        // Fixed order of categories to ensure consistent color mapping
        $categories = ['Café', 'Restoran', 'Sekolah', 'Villa', 'Hotel', 'Popular Spot', 'Lainnya'];
        $trendsData = [];

        if ($period === 'weekly') {
            // Get weekly trends per category
            for ($i = $weeks - 1; $i >= 0; $i--) {
                $startDate = now()->subWeeks($i)->startOfWeek();
                $endDate = now()->subWeeks($i)->endOfWeek();
                $weekLabel = $startDate->format('M d');

                $weekData = ['period' => $weekLabel];
                
                foreach ($categories as $category) {
                    $count = Business::whereBetween('first_seen', [$startDate, $endDate])
                        ->where(function($q) use ($category) {
                            $q->where('category', 'LIKE', '%' . $category . '%')
                              ->orWhereJsonContains('types', strtolower($category));
                        })
                        ->count();
                    
                    $weekData[$category] = $count;
                }
                
                $trendsData[] = $weekData;
            }
        } else {
            // Monthly trends
            $months = min($weeks, 12);
            for ($i = $months - 1; $i >= 0; $i--) {
                $startDate = now()->subMonths($i)->startOfMonth();
                $endDate = now()->subMonths($i)->endOfMonth();
                $monthLabel = $startDate->format('M Y');

                $monthData = ['period' => $monthLabel];
                
                foreach ($categories as $category) {
                    $count = Business::whereBetween('first_seen', [$startDate, $endDate])
                        ->where(function($q) use ($category) {
                            $q->where('category', 'LIKE', '%' . $category . '%')
                              ->orWhereJsonContains('types', strtolower($category));
                        })
                        ->count();
                    
                    $monthData[$category] = $count;
                }
                
                $trendsData[] = $monthData;
            }
        }

        return response()->json([
            'period' => $period,
            'categories' => $categories,
            'trends' => $trendsData,
        ]);
    }

    /**
     * Get trends per kecamatan (Top 5)
     * Sesuai Brief: Tren mingguan penambahan per kecamatan
     */
    public function trendsPerKecamatan(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:weekly,monthly',
            'weeks' => 'nullable|integer|min:1|max:52',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $period = $request->get('period', 'weekly');
        $weeks = $request->get('weeks', 12);
        $limit = $request->get('limit', 5);

        // Get top 5 kecamatan by total new businesses (with area cleaning)
        $rawAreas = Business::selectRaw('
                area,
                COUNT(*) as total_new
            ')
            ->where('first_seen', '>=', now()->subWeeks($weeks))
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('total_new', 'desc')
            ->get();

        // Clean and aggregate areas
        $cleanedAreas = [];
        foreach ($rawAreas as $item) {
            $cleanArea = $this->cleanAreaName($item->area);
            if (!isset($cleanedAreas[$cleanArea])) {
                $cleanedAreas[$cleanArea] = 0;
            }
            $cleanedAreas[$cleanArea] += $item->total_new;
        }

        // Sort by total and get top 5 - ensure consistent order
        arsort($cleanedAreas);
        $topKecamatan = array_slice(array_keys($cleanedAreas), 0, $limit);
        
        // Ensure consistent order for color mapping

        $trendsData = [];

        if ($period === 'weekly') {
            // Weekly trends for top kecamatan
            for ($i = $weeks - 1; $i >= 0; $i--) {
                $startDate = now()->subWeeks($i)->startOfWeek();
                $endDate = now()->subWeeks($i)->endOfWeek();
                $weekLabel = $startDate->format('M d');

                $weekData = ['period' => $weekLabel];
                
                foreach ($topKecamatan as $kecamatan) {
                    // Get all original area names that match this cleaned area
                    $originalAreas = $this->getOriginalAreaNames($kecamatan);
                    
                    $count = Business::whereBetween('first_seen', [$startDate, $endDate])
                        ->whereIn('area', $originalAreas)
                        ->count();
                    
                    $weekData[$kecamatan] = $count;
                }
                
                $trendsData[] = $weekData;
            }
        } else {
            // Monthly trends
            $months = min($weeks, 12);
            for ($i = $months - 1; $i >= 0; $i--) {
                $startDate = now()->subMonths($i)->startOfMonth();
                $endDate = now()->subMonths($i)->endOfMonth();
                $monthLabel = $startDate->format('M Y');

                $monthData = ['period' => $monthLabel];
                
                foreach ($topKecamatan as $kecamatan) {
                    // Get all original area names that match this cleaned area
                    $originalAreas = $this->getOriginalAreaNames($kecamatan);
                    
                    $count = Business::whereBetween('first_seen', [$startDate, $endDate])
                        ->whereIn('area', $originalAreas)
                        ->count();
                    
                    $monthData[$kecamatan] = $count;
                }
                
                $trendsData[] = $monthData;
            }
        }

        return response()->json([
            'period' => $period,
            'kecamatan' => $topKecamatan, // Already an array
            'trends' => $trendsData,
        ]);
    }

    /**
     * Get weekly trends
     */
    private function getWeeklyTrends(Carbon $startDate): array
    {
        return Business::selectRaw('
                DATE(DATE_SUB(first_seen, INTERVAL WEEKDAY(first_seen) DAY)) as week_start,
                COUNT(*) as new_businesses,
                SUM(CASE WHEN JSON_EXTRACT(indicators, "$.recently_opened") = true THEN 1 ELSE 0 END) as recently_opened,
                AVG(JSON_EXTRACT(indicators, "$.new_business_confidence")) as avg_confidence
            ')
            ->where('first_seen', '>=', $startDate)
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->get();
    }

    /**
     * Get monthly trends
     */
    private function getMonthlyTrends(Carbon $startDate): array
    {
        return Business::selectRaw('
                DATE_FORMAT(first_seen, "%Y-%m") as month,
                COUNT(*) as new_businesses,
                SUM(CASE WHEN JSON_EXTRACT(indicators, "$.recently_opened") = true THEN 1 ELSE 0 END) as recently_opened,
                AVG(JSON_EXTRACT(indicators, "$.new_business_confidence")) as avg_confidence
            ')
            ->where('first_seen', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Get categories breakdown
     */
    private function getCategoriesBreakdown(Carbon $startDate): array
    {
        return Business::selectRaw('
                category,
                COUNT(*) as total,
                SUM(CASE WHEN first_seen >= ? THEN 1 ELSE 0 END) as new_count,
                AVG(JSON_EXTRACT(indicators, "$.new_business_confidence")) as avg_confidence
            ')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderBy('new_count', 'desc')
            ->setBindings([$startDate])
            ->get();
    }

    /**
     * Get areas breakdown (with area cleaning)
     */
    private function getAreasBreakdown(Carbon $startDate): array
    {
        $rawAreas = Business::selectRaw('
                area,
                COUNT(*) as total,
                SUM(CASE WHEN first_seen >= ? THEN 1 ELSE 0 END) as new_count,
                AVG(JSON_EXTRACT(indicators, "$.new_business_confidence")) as avg_confidence
            ')
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->groupBy('area')
            ->orderBy('new_count', 'desc')
            ->setBindings([$startDate])
            ->get();

        // Clean and aggregate areas
        $cleanedAreas = [];
        foreach ($rawAreas as $item) {
            $cleanArea = $this->cleanAreaName($item->area);
            
            if (!isset($cleanedAreas[$cleanArea])) {
                $cleanedAreas[$cleanArea] = [
                    'area' => $cleanArea,
                    'total' => 0,
                    'new_count' => 0,
                    'avg_confidence' => 0,
                    'confidence_sum' => 0,
                    'confidence_count' => 0
                ];
            }
            
            $cleanedAreas[$cleanArea]['total'] += $item->total;
            $cleanedAreas[$cleanArea]['new_count'] += $item->new_count;
            $cleanedAreas[$cleanArea]['confidence_sum'] += $item->avg_confidence * $item->total;
            $cleanedAreas[$cleanArea]['confidence_count'] += $item->total;
        }

        // Calculate final averages and sort
        foreach ($cleanedAreas as &$area) {
            $area['avg_confidence'] = $area['confidence_count'] > 0 
                ? $area['confidence_sum'] / $area['confidence_count'] 
                : 0;
            unset($area['confidence_sum'], $area['confidence_count']);
        }

        // Sort by new_count descending
        usort($cleanedAreas, function($a, $b) {
            return $b['new_count'] - $a['new_count'];
        });

        return $cleanedAreas;
    }

    /**
     * Calculate growth rate
     */
    private function calculateGrowthRate(int $period): float
    {
        $currentPeriod = Business::where('first_seen', '>=', now()->subDays($period))->count();
        $previousPeriod = Business::whereBetween('first_seen', [
            now()->subDays($period * 2),
            now()->subDays($period)
        ])->count();

        if ($previousPeriod === 0) {
            return $currentPeriod > 0 ? 100 : 0;
        }

        return (($currentPeriod - $previousPeriod) / $previousPeriod) * 100;
    }

    /**
     * Get API usage summary
     */
    private function getApiUsageSummary(Carbon $startDate): array
    {
        $sessions = ScrapeSession::where('started_at', '>=', $startDate)->get();

        return [
            'total_sessions' => $sessions->count(),
            'total_api_calls' => $sessions->sum('api_calls_count'),
            'total_cost' => $sessions->sum('estimated_cost'),
            'average_cost_per_session' => $sessions->avg('estimated_cost'),
        ];
    }
}
