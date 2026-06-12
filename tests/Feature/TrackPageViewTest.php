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
    expect($event->properties)->toBe([
        'route' => 'welcome',
        'path' => 'welcome',
    ]);
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
