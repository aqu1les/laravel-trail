<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Trail\Trail\Http\Middleware\TrackPageView;
use Trail\Trail\Models\TrailEvent;

it('records a page.viewed event for matched routes', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.ignore', ['trail*']);
    Route::middleware(['web', TrackPageView::class])->get('/welcome', fn () => 'ok');
    $this->get('/welcome')->assertOk();
    expect(TrailEvent::where('name', 'page.viewed')->count())->toBe(1);
});

it('does not track ignored routes', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.ignore', ['admin*']);
    Route::middleware(['web', TrackPageView::class])->get('/admin/panel', fn () => 'ok');
    $this->get('/admin/panel')->assertOk();
    expect(TrailEvent::where('name', 'page.viewed')->count())->toBe(0);
});
