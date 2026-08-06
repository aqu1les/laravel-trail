<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

afterEach(fn () => Trail\Trail\Trail::trackPageViewsUsing(null));

it('counts a top level document navigation', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest(['Sec-Fetch-Dest' => 'document'])))->toBeTrue();
});

it('does not count a background fetch', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest(['Sec-Fetch-Dest' => 'empty'])))->toBeFalse();
});

it('does not count a non GET request', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest(['Sec-Fetch-Dest' => 'document'], 'POST')))->toBeFalse();
});

it('counts an inertia visit', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'X-Inertia' => 'true',
        'Sec-Fetch-Dest' => 'empty',
    ])))->toBeTrue();
});

it('does not count an inertia partial reload', function (string $header) {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'X-Inertia' => 'true',
        'Sec-Fetch-Dest' => 'empty',
        $header => 'stats',
    ])))->toBeFalse();
})->with([
    'X-Inertia-Partial-Data',
    'X-Inertia-Partial-Except',
    'X-Inertia-Partial-Component',
]);

it('does not count an inertia reload or poll', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'X-Inertia' => 'true',
        'Sec-Fetch-Dest' => 'empty',
        'Cache-Control' => 'no-cache',
    ])))->toBeFalse();
});

it('does not count an inertia prefetch', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'X-Inertia' => 'true',
        'Sec-Fetch-Dest' => 'empty',
        'Purpose' => 'prefetch',
    ])))->toBeFalse();
});

it('counts a livewire navigate visit', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'X-Livewire-Navigate' => '1',
        'Sec-Fetch-Dest' => 'empty',
    ])))->toBeTrue();
});

it('falls back to accept when the client sends no fetch metadata', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ])))->toBeTrue();
});

it('does not count a fetch that sends no fetch metadata', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest(['Accept' => '*/*'])))->toBeFalse();
});

it('does not count a request with neither fetch metadata nor an html accept', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest()))->toBeFalse();
});

it('ignores the accept fallback when fetch metadata is present', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'Sec-Fetch-Dest' => 'empty',
        'Accept' => 'text/html',
    ])))->toBeFalse();
});

it('does not count a browser prefetch that sends Sec-Purpose: prefetch', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'Sec-Fetch-Dest' => 'document',
        'Sec-Purpose' => 'prefetch',
    ])))->toBeFalse();
});

it('does not count a browser prerender that sends Sec-Purpose: prefetch;prerender', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'Sec-Fetch-Dest' => 'document',
        'Sec-Purpose' => 'prefetch;prerender',
    ])))->toBeFalse();
});

it('does not count a browser prefetch that sends the legacy Purpose: prefetch', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'Sec-Fetch-Dest' => 'document',
        'Purpose' => 'prefetch',
    ])))->toBeFalse();
});

it('counts a plain document navigation with neither prefetch header', function () {
    expect(navigationDetectorSaysYes(navigationDetectorRequest([
        'Sec-Fetch-Dest' => 'document',
    ])))->toBeTrue();
});

it('uses the registered closure instead of the default rule', function () {
    Trail\Trail\Trail::trackPageViewsUsing(fn () => true);

    // A background fetch, which the default rule rejects.
    expect(Trail\Trail\Trail::isNavigation(
        navigationDetectorRequest(['Sec-Fetch-Dest' => 'empty']),
        new Response('ok'),
    ))->toBeTrue();
});

it('passes both the request and the response to the closure', function () {
    $seen = null;

    Trail\Trail\Trail::trackPageViewsUsing(function ($request, $response) use (&$seen) {
        $seen = [$request->getMethod(), $response->getStatusCode()];

        return true;
    });

    Trail\Trail\Trail::isNavigation(navigationDetectorRequest(), new Response('ok', 302));

    expect($seen)->toBe(['GET', 302]);
});

it('falls back to the default rule when no closure is registered', function () {
    Trail\Trail\Trail::trackPageViewsUsing(null);

    expect(Trail\Trail\Trail::isNavigation(
        navigationDetectorRequest(['Sec-Fetch-Dest' => 'document']),
        new Response('ok'),
    ))->toBeTrue();
});
