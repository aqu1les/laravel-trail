<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Trail\Trail\Support\NavigationDetector;

/**
 * @param  array<string, string>  $headers
 */
function navRequest(array $headers = [], string $method = 'GET'): Request
{
    $request = Request::create('/dashboard', $method);

    // Request::create() seeds a browser-ish Accept. Clear it so each case is explicit.
    $request->headers->remove('Accept');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

function isNavigation(Request $request): bool
{
    return (new NavigationDetector)->isNavigation($request, new Response('ok'));
}

it('counts a top level document navigation', function () {
    expect(isNavigation(navRequest(['Sec-Fetch-Dest' => 'document'])))->toBeTrue();
});

it('does not count a background fetch', function () {
    expect(isNavigation(navRequest(['Sec-Fetch-Dest' => 'empty'])))->toBeFalse();
});

it('does not count a non GET request', function () {
    expect(isNavigation(navRequest(['Sec-Fetch-Dest' => 'document'], 'POST')))->toBeFalse();
});

it('counts an inertia visit', function () {
    expect(isNavigation(navRequest([
        'X-Inertia' => 'true',
        'Sec-Fetch-Dest' => 'empty',
    ])))->toBeTrue();
});

it('does not count an inertia partial reload', function (string $header) {
    expect(isNavigation(navRequest([
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
    expect(isNavigation(navRequest([
        'X-Inertia' => 'true',
        'Sec-Fetch-Dest' => 'empty',
        'Cache-Control' => 'no-cache',
    ])))->toBeFalse();
});

it('does not count an inertia prefetch', function () {
    expect(isNavigation(navRequest([
        'X-Inertia' => 'true',
        'Sec-Fetch-Dest' => 'empty',
        'Purpose' => 'prefetch',
    ])))->toBeFalse();
});

it('counts a livewire navigate visit', function () {
    expect(isNavigation(navRequest([
        'X-Livewire-Navigate' => '1',
        'Sec-Fetch-Dest' => 'empty',
    ])))->toBeTrue();
});

it('falls back to accept when the client sends no fetch metadata', function () {
    expect(isNavigation(navRequest([
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ])))->toBeTrue();
});

it('does not count a fetch that sends no fetch metadata', function () {
    expect(isNavigation(navRequest(['Accept' => '*/*'])))->toBeFalse();
});

it('does not count a request with neither fetch metadata nor an html accept', function () {
    expect(isNavigation(navRequest()))->toBeFalse();
});

it('ignores the accept fallback when fetch metadata is present', function () {
    expect(isNavigation(navRequest([
        'Sec-Fetch-Dest' => 'empty',
        'Accept' => 'text/html',
    ])))->toBeFalse();
});
