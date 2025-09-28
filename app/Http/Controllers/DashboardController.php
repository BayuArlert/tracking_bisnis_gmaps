<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;

class DashboardController extends Controller
{
    public function stats()
    {
        $totalNew = Business::count();
        $weeklyGrowth = Business::where('created_at', '>=', now()->subWeek())->count();
        $topCategory = Business::select('category')
            ->groupBy('category')
            ->orderByRaw('COUNT(*) DESC')
            ->first()?->category ?? 'N/A';
        $topArea = Business::select('area')
            ->groupBy('area')
            ->orderByRaw('COUNT(*) DESC')
            ->first()?->area ?? 'N/A';
        $recentBusinesses = Business::latest('created_at')->take(5)->get();

        return response()->json([
            'total_new_businesses' => $totalNew,
            'weekly_growth' => $weeklyGrowth,
            'top_category' => $topCategory,
            'top_area' => $topArea,
            'recent_businesses' => $recentBusinesses,
        ]);
    }
}
