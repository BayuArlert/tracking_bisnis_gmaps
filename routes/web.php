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

// Protected routes - require authentication (temporarily disabled for testing)
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/businesslist', function () {
    return Inertia::render('BusinessList');
});


Route::get('/statistics', function () {
    return Inertia::render('Statistics');
})->name('statistics');

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
