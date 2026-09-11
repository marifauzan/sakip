<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\KinerjaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

    Route::get('/kinerja', [KinerjaController::class, 'index'])->name('kinerja.index');
    Route::post('/kinerja', [KinerjaController::class, 'store'])->name('kinerja.store');
    Route::get('/kinerja/{tree}', [KinerjaController::class, 'show'])->name('kinerja.show');
    Route::post('/kinerja/{tree}/nodes', [KinerjaController::class, 'storeNode'])->name('kinerja.nodes.store');
    Route::post('/kinerja/{tree}/links', [KinerjaController::class, 'storeLink'])->name('kinerja.links.store');
    Route::post('/kinerja/{tree}/nodes/{node}/indicators', [KinerjaController::class, 'storeIndicator'])->name('kinerja.indicators.store');
});
