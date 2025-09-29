<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;

class DashboardController extends Controller
{
    public function stats()
    {
        // Total bisnis baru (berdasarkan first_seen, bukan created_at)
        $totalNew = Business::count();
        
        // Pertumbuhan mingguan berdasarkan first_seen
        $weeklyGrowth = Business::where('first_seen', '>=', now()->subWeek())->count();
        $previousWeekGrowth = Business::whereBetween('first_seen', [now()->subWeeks(2), now()->subWeek()])->count();
        $growthRate = $previousWeekGrowth > 0 ? round((($weeklyGrowth - $previousWeekGrowth) / $previousWeekGrowth) * 100, 1) : 0;
        
        // Kategori terpopuler
        $topCategory = Business::select('category')
            ->groupBy('category')
            ->orderByRaw('COUNT(*) DESC')
            ->first()?->category ?? 'N/A';
            
        // Area terpopuler
        $topArea = Business::select('area')
            ->groupBy('area')
            ->orderByRaw('COUNT(*) DESC')
            ->first()?->area ?? 'N/A';
            
        // Hitung bisnis dengan indikator khusus
        $recentlyOpenedCount = Business::whereJsonContains('indicators->recently_opened', true)->count();
        $trendingCount = Business::whereJsonContains('indicators->review_spike', true)->count();
        
        // Bisnis terbaru dengan indikator
        $recentBusinesses = Business::latest('first_seen')
            ->take(10)
            ->get()
            ->map(function ($business) {
                // Tambahkan indikator bisnis baru
                $indicators = $business->indicators ?? [];
                $business->recently_opened = $indicators['recently_opened'] ?? false;
                $business->review_spike = $indicators['review_spike'] ?? false;
                $business->few_reviews = $business->review_count < 10;
                $business->has_recent_photo = $indicators['has_recent_photo'] ?? false;
                return $business;
            });

        return response()->json([
            'total_new_businesses' => $totalNew,
            'weekly_growth' => $weeklyGrowth,
            'growth_rate' => $growthRate,
            'top_category' => $topCategory,
            'top_area' => $topArea,
            'recently_opened_count' => $recentlyOpenedCount,
            'trending_count' => $trendingCount,
            'recent_businesses' => $recentBusinesses,
        ]);
    }

}
