<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Trail\Trail\Trail;

afterEach(function () {
    Trail::auth(null);
    Trail::ingestUsing(null);

    // Restore the default route collection so route mutations do not leak.
    Route::setRoutes(new RouteCollection);
    Trail::routes();
    app('router')->getRoutes()->refreshNameLookups();
});

/** Re-run Trail's route registration against the current config. */
function reregisterTrailRoutes(): void
{
    Route::setRoutes(new RouteCollection);
    Trail::routes();
    app('router')->getRoutes()->refreshNameLookups();
}

it('allows ingest by default, including anonymous', function () {
    expect(Trail::canIngest(request()))->toBeTrue();
});

it('honors a custom ingest gate', function () {
    Trail::ingestUsing(fn () => false);

    expect(Trail::canIngest(request()))->toBeFalse();
});

it('keeps the view gate independent from the ingest gate', function () {
    Trail::auth(fn () => false);
    Trail::ingestUsing(fn () => true);

    expect(Trail::check(request()))->toBeFalse()
        ->and(Trail::canIngest(request()))->toBeTrue();
});

it('exposes the ingest route by default', function () {
    expect(app('router')->getRoutes()->hasNamedRoute('trail.api.ingest'))->toBeTrue();
});

it('removes all API routes when api.enabled is false', function () {
    config()->set('trail.api.enabled', false);
    reregisterTrailRoutes();

    $names = collect(app('router')->getRoutes())->map->getName()->filter()->all();

    expect($names)->not->toContain('trail.api.events')
        ->and($names)->not->toContain('trail.api.ingest');
});

it('keeps the read API but removes ingest when browser.enabled is false', function () {
    config()->set('trail.browser.enabled', false);
    reregisterTrailRoutes();

    expect(app('router')->getRoutes()->hasNamedRoute('trail.api.events'))->toBeTrue()
        ->and(app('router')->getRoutes()->hasNamedRoute('trail.api.ingest'))->toBeFalse();
});

it('registers under a custom group via Trail::routes() options', function () {
    Route::setRoutes(new RouteCollection);
    Trail::routes(['prefix' => 'metrics-admin', 'middleware' => ['web']]);
    app('router')->getRoutes()->refreshNameLookups();

    $route = app('router')->getRoutes()->getByName('trail.api.ingest');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('metrics-admin/api/ingest');
});
