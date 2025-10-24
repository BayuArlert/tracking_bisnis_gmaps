<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ScrapingOrchestratorService;
use App\Models\ScrapeSession;
use Illuminate\Support\Facades\Log;

class ScrapeController extends Controller
{
    private ScrapingOrchestratorService $scrapingOrchestrator;

    public function __construct(ScrapingOrchestratorService $scrapingOrchestrator)
    {
        $this->scrapingOrchestrator = $scrapingOrchestrator;
    }

    /**
     * Start manual scraping
     */
    public function start(Request $request)
    {
        $request->validate([
            'type' => 'required|in:initial,weekly',
            'area' => 'required_if:type,initial|string',
            'categories' => 'nullable|array',
            'categories.*' => 'string|in:Café,Restoran,Sekolah,Villa,Hotel,Popular Spot,Lainnya',
        ]);

        try {
            if ($request->type === 'initial') {
                $session = $this->scrapingOrchestrator->startInitialScraping(
                    $request->area,
                    $request->categories ?? []
                );
            } else {
                $session = $this->scrapingOrchestrator->startWeeklyUpdate(
                    $request->areas ?? [],
                    $request->categories ?? []
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Scraping started successfully',
                'session' => $session,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start scraping: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to start scraping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get scraping session status
     */
    public function status($id)
    {
        $session = ScrapeSession::findOrFail($id);

        return response()->json([
            'session' => $session,
            'progress' => $this->calculateProgress($session),
        ]);
    }

    /**
     * Get scraping sessions history
     */
    public function sessions(Request $request)
    {
        $query = ScrapeSession::query();

        if ($request->has('type')) {
            $query->where('session_type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('started_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('started_at', '<=', $request->date_to);
        }

        $sessions = $query->orderBy('started_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($sessions);
    }

    /**
     * Get scraping statistics
     */
    public function statistics()
    {
        $stats = [
            'total_sessions' => ScrapeSession::count(),
            'successful_sessions' => ScrapeSession::where('status', 'completed')->count(),
            'failed_sessions' => ScrapeSession::where('status', 'failed')->count(),
            'total_api_calls' => ScrapeSession::sum('api_calls_count'),
            'total_cost' => ScrapeSession::sum('estimated_cost'),
            'total_businesses_found' => ScrapeSession::sum('businesses_found'),
            'total_businesses_new' => ScrapeSession::sum('businesses_new'),
            'average_cost_per_session' => ScrapeSession::avg('estimated_cost'),
            'average_businesses_per_session' => ScrapeSession::avg('businesses_found'),
        ];

        // Recent sessions (last 7 days)
        $recentSessions = ScrapeSession::where('started_at', '>=', now()->subDays(7))->get();
        $stats['recent_sessions'] = [
            'count' => $recentSessions->count(),
            'total_cost' => $recentSessions->sum('estimated_cost'),
            'total_businesses_new' => $recentSessions->sum('businesses_new'),
        ];

        // Monthly trend
        $monthlyStats = ScrapeSession::selectRaw('
                DATE_FORMAT(started_at, "%Y-%m") as month,
                COUNT(*) as sessions_count,
                SUM(estimated_cost) as total_cost,
                SUM(businesses_new) as total_new_businesses
            ')
            ->where('started_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $stats['monthly_trend'] = $monthlyStats;

        return response()->json($stats);
    }

    /**
     * Cancel a running scraping session
     */
    public function cancel($id)
    {
        $session = ScrapeSession::findOrFail($id);

        if ($session->status !== 'running') {
            return response()->json([
                'success' => false,
                'message' => 'Session is not running',
            ], 400);
        }

        $session->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_log' => 'Cancelled by user',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session cancelled successfully',
        ]);
    }

    /**
     * Get available regions for scraping
     */
    public function regions()
    {
        $regions = \App\Models\BaliRegion::where('type', 'kabupaten')
            ->orderBy('priority')
            ->get(['id', 'name', 'priority']);

        return response()->json($regions);
    }

    /**
     * Get available categories for scraping
     */
    public function categories()
    {
        $categories = \App\Models\CategoryMapping::pluck('brief_category');

        return response()->json($categories);
    }

    /**
     * Start NEW BUSINESS ONLY scraping
     */
    public function startNewBusinessScraping(Request $request)
    {
        $request->validate([
            'area' => 'required|string',
            'categories' => 'nullable|array',
            'categories.*' => 'string|in:Café,Restoran,Sekolah,Villa,Hotel,Popular Spot,Lainnya',
            'confidence_threshold' => 'nullable|integer|min:0|max:100',
            'use_adaptive_threshold' => 'nullable|boolean', // NEW
        ]);

        try {
            $baseThreshold = $request->confidence_threshold ?? 60; // Changed from 75 to 60
            
            // Log strategy
            Log::info("Starting Selective Viral Detection scraping strategy", [
                'area' => $request->area,
                'base_threshold' => $baseThreshold,
                'strategy' => 'v4.1-selective',
                'features' => [
                    'review_count_prefiltering',
                    'selective_viral_detection',
                    'spike_analysis',
                    'photo_validation',
                    'adaptive_threshold'
                ]
            ]);
            
            $session = $this->scrapingOrchestrator->startNewBusinessOnlyScraping(
                $request->area,
                $request->categories ?? [],
                $baseThreshold
            );

            return response()->json([
                'success' => true,
                'message' => 'Selective viral detection scraping started',
                'session' => $session,
                'strategy' => [
                    'approach' => 'Review-First + Selective Viral Detection + Photo Validation',
                    'version' => 'v4.1-selective',
                    'base_threshold' => $baseThreshold,
                    'adaptive_review_threshold' => true,
                    'expected_cost_per_kabupaten' => '$4-4.50',
                    'expected_results' => '12-16 new businesses',
                    'expected_success_rate' => '82-88%',
                    'cost_increase' => '+35% vs v4.0 (not +112%)'
                ],
                'features' => [
                    'review_count_prefiltering' => 'Filter by review count < threshold (FREE)',
                    'selective_viral_detection' => 'Check 16-100 reviews ONLY if has keywords (cost efficient)',
                    'viral_keywords' => 'new, baru, grand opening, 2024, 2025, etc. (universal)',
                    'spike_analysis' => 'Detect 30+ reviews in < 90 days (viral pattern)',
                    'photo_validation' => 'Cross-validate photo timestamps (filter false positives)',
                    'adaptive_threshold' => 'Tourist: <15, Medium: <12, Remote: <20',
                    'review_date_validation' => 'Confirm < 6 months OR has spike',
                    'minimal_fields' => 'Essential fields only',
                    'accurate_tracking' => 'Track all API calls',
                    'universal_logic' => 'Works for Bali, Jawa, all regions'
                ],
                'cost_efficiency' => [
                    'vs_full_scraping' => '80-85% cheaper',
                    'vs_v4.0' => '+35% cost for +7% coverage',
                    'roi' => 'Best balance for production'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start new business scraping: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to start new business scraping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate progress for a session
     */
    private function calculateProgress(ScrapeSession $session): array
    {
        if ($session->status === 'completed') {
            return [
                'percentage' => 100,
                'status' => 'completed',
                'message' => 'Scraping completed successfully',
            ];
        }

        if ($session->status === 'failed') {
            return [
                'percentage' => 0,
                'status' => 'failed',
                'message' => 'Scraping failed: ' . ($session->error_log ?? 'Unknown error'),
            ];
        }

        // For running sessions, estimate progress based on time elapsed
        $startTime = $session->started_at;
        $elapsed = now()->diffInMinutes($startTime);
        
        // Estimate based on session type
        $estimatedDuration = $session->session_type === 'initial' ? 60 : 30; // minutes
        $percentage = min(90, ($elapsed / $estimatedDuration) * 100);

        return [
            'percentage' => $percentage,
            'status' => 'running',
            'message' => 'Scraping in progress...',
            'elapsed_minutes' => $elapsed,
        ];
    }
}
