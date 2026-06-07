<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Trail\Trail\Http\Controllers\Api\EventsController;
use Trail\Trail\Http\Controllers\Api\FunnelController;
use Trail\Trail\Http\Controllers\Api\MetricsController;
use Trail\Trail\Http\Controllers\AssetController;
use Trail\Trail\Http\Controllers\DashboardController;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Livewire\SubjectTimeline;

// Embedded design-system stylesheet — served so the dashboard renders
// on a fresh install without `vendor:publish`.
Route::get('trail.css', [AssetController::class, 'css'])->name('styles');

// Dashboard screens — full-page Livewire components consuming the design system.
Route::get('/', Overview::class)->name('dashboard');
Route::get('events', Events::class)->name('events');
Route::get('timeline', SubjectTimeline::class)->name('timeline');
// Static design-system showcase (no Livewire needed).
Route::get('design-system', [DashboardController::class, 'designSystem'])->name('design-system');

Route::prefix('api')->name('api.')->group(function () {
    Route::get('events', [EventsController::class, 'index'])->name('events');
    Route::get('metrics', [MetricsController::class, 'index'])->name('metrics');
    Route::get('funnel', [FunnelController::class, 'show'])->name('funnel');
});
