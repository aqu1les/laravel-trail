<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Trail\Trail\Http\Controllers\Api\EventsController;
use Trail\Trail\Http\Controllers\Api\FunnelController;
use Trail\Trail\Http\Controllers\Api\MetricsController;
use Trail\Trail\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('api')->name('api.')->group(function () {
    Route::get('events', [EventsController::class, 'index'])->name('events');
    Route::get('metrics', [MetricsController::class, 'index'])->name('metrics');
    Route::get('funnel', [FunnelController::class, 'show'])->name('funnel');
});
