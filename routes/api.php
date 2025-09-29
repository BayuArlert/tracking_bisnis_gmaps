<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\NotificationController;

// API Authentication routes (for frontend API calls)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/auth/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/auth/login', [App\Http\Controllers\AuthController::class, 'login']);
    Route::post('/auth/logout', [App\Http\Controllers\AuthController::class, 'logout']);
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
    Route::get('/export/csv', [BusinessController::class, 'exportCSV']);
    
    // Notification routes
    Route::post('/notifications/weekly-summary', [NotificationController::class, 'sendWeeklySummary']);
    Route::post('/notifications/monthly-summary', [NotificationController::class, 'sendMonthlySummary']);
    Route::post('/notifications/schedule', [NotificationController::class, 'scheduleNotifications']);
});

// Public route for fetching new businesses (for monitoring)
Route::get('/businesses/new', [BusinessController::class, 'fetchNew']);

