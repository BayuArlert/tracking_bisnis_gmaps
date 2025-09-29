<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Inertia\Inertia;

// Home route
Route::get('/', function () {
    return redirect('/dashboard');
})->name('home');

// Login page (public)
Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login')->middleware('guest');

// Protected routes - require authentication
Route::middleware('auth:web')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/businesslist', function () {
        return Inertia::render('BusinessList');
    });

    Route::get('/statistics', function () {
        return Inertia::render('Statistics');
    })->name('statistics');
});

// Logout moved to API routes
