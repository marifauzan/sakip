<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\KinerjaController;
use App\Http\Controllers\KnowledgePackController;
use App\Http\Controllers\ReviewController;
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
    Route::post('/documents/{document}/retry-extract', [DocumentController::class, 'retryExtract'])->name('documents.retry-extract');
    Route::post('/documents/{document}/detect-sector', [DocumentController::class, 'detectSector'])->name('documents.detect-sector');
    Route::post('/documents/{document}/confirm-sector', [DocumentController::class, 'confirmSector'])->name('documents.confirm-sector');

    Route::get('/kinerja', [KinerjaController::class, 'index'])->name('kinerja.index');
    Route::post('/kinerja', [KinerjaController::class, 'store'])->name('kinerja.store');
    Route::get('/kinerja/{tree}', [KinerjaController::class, 'show'])->name('kinerja.show');
    Route::post('/kinerja/{tree}/nodes', [KinerjaController::class, 'storeNode'])->name('kinerja.nodes.store');
    Route::post('/kinerja/{tree}/links', [KinerjaController::class, 'storeLink'])->name('kinerja.links.store');
    Route::post('/kinerja/{tree}/positions', [KinerjaController::class, 'savePositions'])->name('kinerja.positions.save');
    Route::post('/kinerja/{tree}/nodes/{node}/indicators', [KinerjaController::class, 'storeIndicator'])->name('kinerja.indicators.store');

    // AI
    Route::post('/kinerja/{tree}/nodes/{node}/ai/children', [AiController::class, 'recommendChildren'])->name('kinerja.ai.children');
    Route::post('/kinerja/{tree}/nodes/{node}/ai/indicators', [AiController::class, 'recommendIndicators'])->name('kinerja.ai.indicators');
    Route::post('/kinerja/{tree}/nodes/{node}/ai/children/accept', [AiController::class, 'acceptChildren'])->name('kinerja.ai.children.accept');
    Route::post('/kinerja/{tree}/nodes/{node}/ai/indicators/accept', [AiController::class, 'acceptIndicator'])->name('kinerja.ai.indicators.accept');
    Route::post('/kinerja/{tree}/nodes/{node}/ai/dismiss', [AiController::class, 'dismissRecommendations'])->name('kinerja.ai.dismiss');

    // Reviu & ekspor
    Route::post('/kinerja/{tree}/reviews', [ReviewController::class, 'store'])->name('kinerja.reviews.store');
    Route::get('/kinerja/{tree}/export', [ReviewController::class, 'export'])->name('kinerja.export');

    // Knowledge Packs & Sektor
    Route::get('/knowledge-packs', [KnowledgePackController::class, 'index'])->name('knowledge-packs.index');
    Route::post('/knowledge-packs/sectors', [KnowledgePackController::class, 'storeSector'])->name('knowledge-packs.sectors.store');
    Route::put('/knowledge-packs/sectors/{sector}', [KnowledgePackController::class, 'updateSector'])->name('knowledge-packs.sectors.update');
    Route::delete('/knowledge-packs/sectors/{sector}', [KnowledgePackController::class, 'destroySector'])->name('knowledge-packs.sectors.destroy');
    Route::post('/knowledge-packs/sectors/{sector}/packs', [KnowledgePackController::class, 'storePack'])->name('knowledge-packs.packs.store');
    Route::put('/knowledge-packs/packs/{pack}', [KnowledgePackController::class, 'updatePack'])->name('knowledge-packs.packs.update');
    Route::delete('/knowledge-packs/packs/{pack}', [KnowledgePackController::class, 'destroyPack'])->name('knowledge-packs.packs.destroy');
    Route::patch('/knowledge-packs/packs/{pack}/toggle', [KnowledgePackController::class, 'togglePack'])->name('knowledge-packs.packs.toggle');
});
