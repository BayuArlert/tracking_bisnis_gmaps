<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get('/businesses/new', [BusinessController::class, 'fetchNew']);
Route::get('/businesses', [BusinessController::class, 'index']);
Route::get('/businesses/filter-options', [BusinessController::class, 'getFilterOptions']);
Route::get('/businesses/update-metadata', [BusinessController::class, 'updateMetadataForExistingData']);
Route::get('/export/csv', [BusinessController::class, 'exportCSV']);

Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

