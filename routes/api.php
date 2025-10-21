<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\RegionController;

// API Authentication routes (for frontend API calls)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/auth/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/auth/login', [App\Http\Controllers\AuthController::class, 'login']);
    Route::post('/auth/logout', [App\Http\Controllers\AuthController::class, 'logout']);
});

// Route to verify token validity (outside auth middleware to avoid circular dependency)
Route::get('/auth/verify', function (Request $request) {
    $authHeader = $request->header('Authorization');
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return response()->json([
            'success' => false,
            'message' => 'No valid token provided'
        ], 401);
    }
    
    $token = substr($authHeader, 7);
    $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    
    if (!$accessToken || !$accessToken->tokenable) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired token'
        ], 401);
    }
    
    $user = $accessToken->tokenable;
    
    return response()->json([
        'success' => true,
        'message' => 'Token is valid',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]
    ]);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $request->user()->id,
                'username' => $request->user()->name,
                'email' => $request->user()->email,
            ]
        ]);
    });
});



// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Dashboard and statistics routes
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/statistics', [StatisticsController::class, 'index']);
    Route::get('/statistics/heatmap', [StatisticsController::class, 'getHeatmapData']);
    
    // Business routes
    Route::get('/businesses', [BusinessController::class, 'index']);
    Route::get('/businesses/filter-options', [BusinessController::class, 'getFilterOptions']);
    Route::get('/businesses/update-metadata', [BusinessController::class, 'updateMetadataForExistingData']);
    Route::get('/businesses/preview-area', [BusinessController::class, 'getPreviewArea']);
    
    // Scraping routes
    Route::post('/scrape/start', [ScrapeController::class, 'start']);
    Route::post('/scrape/new-business', [ScrapeController::class, 'startNewBusinessScraping']);
    Route::get('/scrape/status/{id}', [ScrapeController::class, 'status']);
    Route::get('/scrape/sessions', [ScrapeController::class, 'sessions']);
    Route::get('/scrape/statistics', [ScrapeController::class, 'statistics']);
    Route::post('/scrape/cancel/{id}', [ScrapeController::class, 'cancel']);
    Route::get('/scrape/regions', [ScrapeController::class, 'regions']);
    Route::get('/scrape/categories', [ScrapeController::class, 'categories']);
    
    // Analytics routes
    Route::get('/analytics/trends', [AnalyticsController::class, 'trends']);
    Route::get('/analytics/hot-zones', [AnalyticsController::class, 'hotZones']);
    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('/analytics/category-breakdown', [AnalyticsController::class, 'categoryBreakdown']);
    Route::get('/analytics/area-breakdown', [AnalyticsController::class, 'areaBreakdown']);
    Route::get('/analytics/age-distribution', [AnalyticsController::class, 'ageDistribution']);
    Route::get('/analytics/confidence-distribution', [AnalyticsController::class, 'confidenceDistribution']);
    Route::get('/analytics/trends-per-category', [AnalyticsController::class, 'trendsPerCategory']);
    Route::get('/analytics/trends-per-kecamatan', [AnalyticsController::class, 'trendsPerKecamatan']);
    
    // Export routes
    Route::get('/export/test-csv', [ExportController::class, 'testCsv']); // Test endpoint
    Route::get('/export/csv', [ExportController::class, 'csv']);
    Route::get('/export/json', [ExportController::class, 'json']);
    Route::get('/export/map-image', [ExportController::class, 'mapImage']);
    Route::get('/export/options', [ExportController::class, 'options']);
    
    // Region routes (for hierarchical filters)
    Route::get('/regions/kabupaten', [RegionController::class, 'getKabupaten']);
    Route::get('/regions/kecamatan/{kabupatenName}', [RegionController::class, 'getKecamatan']);
    Route::get('/regions/desa/{kecamatanId}', [RegionController::class, 'getDesa']);
    Route::get('/regions/hierarchy', [RegionController::class, 'getHierarchy']);
    Route::get('/regions/debug-kecamatan/{kabupatenName}', [RegionController::class, 'debugKecamatan']);
    
    // Notification routes
    Route::post('/notifications/weekly-summary', [NotificationController::class, 'sendWeeklySummary']);
    Route::post('/notifications/monthly-summary', [NotificationController::class, 'sendMonthlySummary']);
    Route::post('/notifications/schedule', [NotificationController::class, 'scheduleNotifications']);
});

// Public route for fetching new businesses (for monitoring)
Route::get('/businesses/new', [BusinessController::class, 'fetchNew']);

