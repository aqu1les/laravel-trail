<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Trail\Trail\Http\Controllers\Api\EventsController;
use Trail\Trail\Http\Controllers\Api\FunnelController;
use Trail\Trail\Http\Controllers\Api\MetricsController;
use Trail\Trail\Http\Controllers\AssetController;
use Trail\Trail\Http\Controllers\DashboardController;

// Embedded design-system stylesheet — served so the dashboard renders
// on a fresh install without `vendor:publish`.
Route::get('trail.css', [AssetController::class, 'css'])->name('styles');

// Dashboard screens (server-rendered Blade consuming the design system).
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('events', [DashboardController::class, 'events'])->name('events');
Route::get('timeline', [DashboardController::class, 'timeline'])->name('timeline');
Route::get('design-system', [DashboardController::class, 'designSystem'])->name('design-system');

Route::prefix('api')->name('api.')->group(function () {
    Route::get('events', [EventsController::class, 'index'])->name('events');
    Route::get('metrics', [MetricsController::class, 'index'])->name('metrics');
    Route::get('funnel', [FunnelController::class, 'show'])->name('funnel');
});
