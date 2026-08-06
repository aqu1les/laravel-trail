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

it('records the path as a property, plus route name when the route is named', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.ignore', ['trail*']);
    Route::middleware(['web', TrackPageView::class])->get('/welcome', fn () => 'ok')->name('welcome');

    $this->get('/welcome')->assertOk();

    $event = TrailEvent::firstWhere('name', 'page.viewed');
    expect($event->properties)
        ->toHaveCount(2)
        ->toHaveKey('route', 'welcome')
        ->toHaveKey('path', 'welcome');
});

it('omits the route property for unnamed routes', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.ignore', ['trail*']);
    Route::middleware(['web', TrackPageView::class])->get('/welcome', fn () => 'ok');

    $this->get('/welcome')->assertOk();

    $event = TrailEvent::firstWhere('name', 'page.viewed');
    expect($event->properties)->toBe([
        'path' => 'welcome',
    ]);
});

it('does not track ignored routes', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.ignore', ['admin*']);
    Route::middleware(['web', TrackPageView::class])->get('/admin/panel', fn () => 'ok');
    $this->get('/admin/panel')->assertOk();
    expect(TrailEvent::where('name', 'page.viewed')->count())->toBe(0);
});

it('uses the configured event name for page views', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.page_views', true);
    config()->set('trail.auto_track.event_name', 'visit.tracked');

    Route::middleware(['web', TrackPageView::class])->get('/custom-name', fn () => 'ok');

    $this->get('/custom-name')->assertOk();

    expect(TrailEvent::firstWhere('name', 'visit.tracked'))->not->toBeNull();
});

it('does not record a background fetch', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.ignore', ['trail*']);
    Route::middleware(['web', TrackPageView::class])->get('/api/stats', fn () => 'ok');

    $this->get('/api/stats', ['Sec-Fetch-Dest' => 'empty'])->assertOk();

    expect(TrailEvent::where('name', 'page.viewed')->count())->toBe(0);
});

it('records a request the registered closure accepts', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.ignore', ['trail*']);
    Trail\Trail\Trail::trackPageViewsUsing(fn () => true);
    Route::middleware(['web', TrackPageView::class])->get('/api/stats', fn () => 'ok');

    $this->get('/api/stats', ['Sec-Fetch-Dest' => 'empty'])->assertOk();

    expect(TrailEvent::where('name', 'page.viewed')->count())->toBe(1);

    Trail\Trail\Trail::trackPageViewsUsing(null);
});

it('applies the ignore list even when a permissive closure is registered', function () {
    config()->set('trail.recorder', 'sync');
    config()->set('trail.auto_track.ignore', ['trail*']);
    Trail\Trail\Trail::trackPageViewsUsing(fn () => true);
    Route::middleware(['web', TrackPageView::class])->get('/trail/events', fn () => 'ok');

    $this->get('/trail/events')->assertOk();

    expect(TrailEvent::where('name', 'page.viewed')->count())->toBe(0);

    Trail\Trail\Trail::trackPageViewsUsing(null);
});
