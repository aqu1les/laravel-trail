<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Livewire\Paths;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));

afterEach(function () {
    Trail::auth(null);

    // Routes registered under a forced 'local' env below must not leak into
    // later tests: restore the real environment and re-register against it.
    app()->detectEnvironment(fn () => 'testing');
    Route::setRoutes(new RouteCollection);
    Trail::routes();
    app('router')->getRoutes()->refreshNameLookups();
});

it('passes demo=true from route defaults into a full-page component mount', function () {
    // Same mechanism the /trail/demo routes use. Demo mode seeds 50 events,
    // so the real-data empty state must NOT appear.
    Route::get('/__demo_probe', Events::class)->defaults('demo', true);

    $this->get('/__demo_probe')
        ->assertOk()
        ->assertDontSee('Nenhum evento corresponde');
});

it('passes demo=true from route defaults into the Paths component without touching the database', function () {
    // Same mechanism the /trail/demo/paths route uses.
    Route::get('/__demo_probe_paths', Paths::class)->defaults('demo', true);

    expect(TrailEvent::count())->toBe(0);

    $this->get('/__demo_probe_paths')
        ->assertOk()
        ->assertSee('register', false);

    expect(TrailEvent::count())->toBe(0);
});

it('runs zero database queries while rendering the demo Paths route', function () {
    Route::get('/__demo_probe_paths_queries', Paths::class)->defaults('demo', true);

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->get('/__demo_probe_paths_queries')->assertOk();

    $queries = DB::connection()->getQueryLog();
    DB::connection()->disableQueryLog();

    // TrailEvent::count() only proves nothing was WRITTEN; it is blind to a
    // SELECT. Demo mode is meant to never touch the database at all, so
    // assert on the query log directly.
    expect($queries)->toBe([]);
});

it('registers the real trail.demo.paths route only under the local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    Route::setRoutes(new RouteCollection);
    Trail::routes();
    app('router')->getRoutes()->refreshNameLookups();

    expect(Route::has('trail.demo.paths'))->toBeTrue();

    $this->get(route('trail.demo.paths'))->assertOk();
});

it('does not register the demo Paths route outside the local environment', function () {
    // The default testing environment: the real demo routes must not exist,
    // only this file's own probe routes should.
    expect(Route::has('trail.demo.paths'))->toBeFalse();
});

it('shows the demo Paths link in the sidebar only under the local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    Route::setRoutes(new RouteCollection);
    Trail::routes();
    app('router')->getRoutes()->refreshNameLookups();

    $this->get(route('trail.demo.paths'))
        ->assertOk()
        ->assertSee('href="'.route('trail.demo.paths').'"', false);
});
