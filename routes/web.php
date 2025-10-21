<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use Inertia\Inertia;

// Home route - redirect to dashboard if authenticated, otherwise to login
Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }
    return redirect('/login');
})->name('home');

// Login page (public)
Route::get('/login', function (Request $request) {
    // Check session authentication first
    if (Auth::check()) {
        return redirect('/dashboard');
    }
    
    // Check token authentication
    $authHeader = $request->header('Authorization');
    if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
        $token = substr($authHeader, 7);
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if ($accessToken && $accessToken->tokenable) {
            return redirect('/dashboard');
        }
    }
    
    return Inertia::render('Auth/Login');
})->name('login')->middleware('guest');

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/businesslist', function () {
        return Inertia::render('BusinessList');
    });

    Route::get('/statistics', function () {
        return Inertia::render('Statistics');
    })->name('statistics');

    Route::get('/analytics', function () {
        return Inertia::render('Analytics');
    })->name('analytics');
});

// Logout moved to API routes
