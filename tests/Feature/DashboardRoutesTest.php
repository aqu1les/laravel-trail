<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Trail\Trail\Trail;

afterEach(function () {
    Trail::auth(null);

    // Restore the default route collection so path mutations do not leak.
    config()->set('trail.path', 'trail');
    Route::setRoutes(new RouteCollection);
    Trail::routes();
    app('router')->getRoutes()->refreshNameLookups();
});

it('registers the dashboard route under the configured path', function () {
    Trail::auth(fn () => true);

    $this->get('/trail')->assertOk();
});

it('forbids the dashboard when the auth gate denies', function () {
    Trail::auth(fn () => false);

    $this->get('/trail')->assertForbidden();
});

it('hands the request to the auth gate', function () {
    $seen = null;
    Trail::auth(function ($request) use (&$seen) {
        $seen = $request;

        return true;
    });

    $this->get('/trail')->assertOk();

    expect($seen)->toBeInstanceOf(Request::class)
        ->and($seen->path())->toBe('trail');
});

it('honours a custom path from config', function () {
    config()->set('trail.path', 'insights');

    // The path is read at route-registration time, so re-register against it.
    Route::setRoutes(new RouteCollection);
    Trail::routes();
    app('router')->getRoutes()->refreshNameLookups();

    Trail::auth(fn () => true);

    $this->get('/insights')->assertOk();
    $this->get('/trail')->assertNotFound();
});
