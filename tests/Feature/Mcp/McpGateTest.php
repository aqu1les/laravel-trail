<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Trail\Trail\Trail;

// The testbench environment is "testing" (non-local), so the default gate
// denies unless a callback is registered or the environment is local.

afterEach(function () {
    Trail::auth(null);
    Trail::mcpUsing(null);
});

it('denies by default outside the local environment', function () {
    expect(Trail::canAccessMcp(Request::create('/')))->toBeFalse();
});

it('allows by default in the local environment', function () {
    $this->app['env'] = 'local';

    expect(Trail::canAccessMcp(Request::create('/')))->toBeTrue();
});

it('honors a registered token callback', function () {
    Trail::mcpUsing(fn (Request $request) => hash_equals('secret', (string) $request->bearerToken()));

    $withToken = Request::create('/');
    $withToken->headers->set('Authorization', 'Bearer secret');

    expect(Trail::canAccessMcp($withToken))->toBeTrue();
    expect(Trail::canAccessMcp(Request::create('/')))->toBeFalse();
});

it('is independent of the dashboard auth gate', function () {
    Trail::auth(fn () => true); // dashboard wide open

    expect(Trail::canAccessMcp(Request::create('/')))->toBeFalse();

    Trail::auth(null);
    Trail::mcpUsing(fn () => true); // mcp wide open

    expect(Trail::check(Request::create('/')))->toBeFalse(); // dashboard default-deny outside local
    expect(Trail::canAccessMcp(Request::create('/')))->toBeTrue();
});
