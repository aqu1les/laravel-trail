<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Trail\Trail\Http\Controllers\Api\EventsController;
use Trail\Trail\Http\Controllers\Api\FunnelController;
use Trail\Trail\Http\Controllers\Api\IngestController;
use Trail\Trail\Http\Controllers\Api\MetricsController;
use Trail\Trail\Http\Controllers\DashboardController;
use Trail\Trail\Http\Middleware\Authorize;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Livewire\SubjectTimeline;

// View-gated: dashboard screens + read API. "Who can see the data."
Route::middleware(Authorize::class.':view')->group(function () {
    // Dashboard screens - full-page Livewire components on real tracking data.
    Route::get('/', Overview::class)->name('dashboard');
    Route::get('events', Events::class)->name('events');
    Route::get('timeline', SubjectTimeline::class)->name('timeline');
    // Static design-system showcase (no Livewire needed).
    Route::get('design-system', [DashboardController::class, 'designSystem'])->name('design-system');

    // Demo screens with sample data - local environment only.
    if (app()->environment('local')) {
        Route::get('demo', Overview::class)->defaults('demo', true)->name('demo');
        Route::get('demo/events', Events::class)->defaults('demo', true)->name('demo.events');
        Route::get('demo/timeline', SubjectTimeline::class)->defaults('demo', true)->name('demo.timeline');
    }

    if (config('trail.api.enabled', true)) {
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('events', [EventsController::class, 'index'])->name('events');
            Route::get('metrics', [MetricsController::class, 'index'])->name('metrics');
            Route::get('funnel', [FunnelController::class, 'show'])->name('funnel');
        });
    }
});

// Ingest-gated: browser write endpoint. "Who can emit events."
if (config('trail.api.enabled', true) && config('trail.browser.enabled', true)) {
    Route::prefix('api')->name('api.')->middleware(Authorize::class.':ingest')->group(function () {
        Route::post('ingest', [IngestController::class, 'store'])->name('ingest');
    });
}
