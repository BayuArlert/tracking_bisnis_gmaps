<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/dashboard');
})->name('home');

// halaman login
Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

// halaman dashboard (temporary without auth for testing)
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

// Test route untuk debugging
Route::get('/test-dashboard', function () {
    return response()->json(['message' => 'Dashboard route is working!', 'timestamp' => now()]);
});

Route::get('/businesslist', function () {
    return Inertia::render('BusinessList');
});

Route::get('/businesses', function () {
    return redirect('/businesslist');
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
