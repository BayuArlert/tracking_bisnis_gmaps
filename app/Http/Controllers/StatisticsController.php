<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'weekly');
        $category = $request->get('category', 'all');
        $area = $request->get('area', 'all');

        // Base query
        $query = Business::query();

        // Apply filters
        if ($category !== 'all') {
            $query->where('category', 'like', '%' . $category . '%');
        }

        if ($area !== 'all') {
            $query->where('area', 'like', '%' . $area . '%');
        }

        // Get basic stats
        $totalBusinesses = Business::count();
        $newThisWeek = Business::where('first_seen', '>=', now()->subWeek())->count();
        $newThisMonth = Business::where('first_seen', '>=', now()->subMonth())->count();
        
        // Calculate growth rate
        $previousWeek = Business::whereBetween('first_seen', [now()->subWeeks(2), now()->subWeek()])->count();
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

    private function cleanAreaName($area)
    {
        // Remove numbers and extra spaces from area names
        $clean = preg_replace('/\s+\d+/', '', $area);
        $clean = trim($clean);
        
        // Handle specific cases
        if (strpos($clean, 'Bali') !== false) {
            return 'Bali';
        }
        
        return $clean;
    }

    public function getHeatmapData(Request $request)
    {
        $category = $request->get('category', 'all');
        $area = $request->get('area', 'all');
        $period = $request->get('period', 'monthly');

        $query = Business::query();

        // Apply filters
        if ($category !== 'all') {
            $query->where('category', 'like', '%' . $category . '%');
        }

        if ($area !== 'all') {
            $query->where('area', 'like', '%' . $area . '%');
        }

        // Apply time filter
        if ($period === 'weekly') {
            $query->where('first_seen', '>=', now()->subWeek());
        } elseif ($period === 'monthly') {
            $query->where('first_seen', '>=', now()->subMonth());
        }

        $businesses = $query->whereNotNull('lat')
            ->whereNotNull('lng')
            ->select('lat', 'lng', 'name', 'category', 'area', 'review_count', 'rating')
            ->get();

        return response()->json([
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
}
