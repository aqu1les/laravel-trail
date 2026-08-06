<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Trail\Trail\Support\NavigationDetector;
use Trail\Trail\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Builds a bare GET request for NavigationDetector tests, with the given
 * headers applied and the default Accept header cleared so each case is
 * explicit. Named to avoid colliding with any future test's helpers.
 *
 * @param  array<string, string>  $headers
 */
function navigationDetectorRequest(array $headers = [], string $method = 'GET'): Request
{
    $request = Request::create('/dashboard', $method);

    // Request::create() seeds a browser-ish Accept. Clear it so each case is explicit.
    $request->headers->remove('Accept');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

function navigationDetectorSaysYes(Request $request): bool
{
    return (new NavigationDetector)->isNavigation($request, new Response('ok'));
}
