<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Trail\Trail\Http\Controllers\Api\EventsController;
use Trail\Trail\Http\Controllers\Api\FunnelController;
use Trail\Trail\Http\Controllers\Api\MetricsController;
use Trail\Trail\Http\Controllers\DashboardController;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Livewire\Overview;
use Trail\Trail\Livewire\SubjectTimeline;

// Dashboard screens — full-page Livewire components on real tracking data.
Route::get('/', Overview::class)->name('dashboard');
Route::get('events', Events::class)->name('events');
Route::get('timeline', SubjectTimeline::class)->name('timeline');
// Static design-system showcase (no Livewire needed).
Route::get('design-system', [DashboardController::class, 'designSystem'])->name('design-system');

// Demo screens with sample data — local environment only, for previewing the UI
// without real events. The same components rendered with demo = true.
if (app()->environment('local')) {
    Route::get('demo', Overview::class)->defaults('demo', true)->name('demo');
    Route::get('demo/events', Events::class)->defaults('demo', true)->name('demo.events');
    Route::get('demo/timeline', SubjectTimeline::class)->defaults('demo', true)->name('demo.timeline');
}

Route::prefix('api')->name('api.')->group(function () {
    Route::get('events', [EventsController::class, 'index'])->name('events');
    Route::get('metrics', [MetricsController::class, 'index'])->name('metrics');
    Route::get('funnel', [FunnelController::class, 'show'])->name('funnel');
});
