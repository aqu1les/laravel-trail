<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Trail\Trail\Http\Middleware\Authorize;
use Trail\Trail\Trail;

afterEach(function () {
    Trail::auth(null);
    Trail::mcpUsing(null);
});

function runAuthorize(?string $ability): Response
{
    $middleware = new Authorize;

    /** @var Response $response */
    $response = $middleware->handle(
        Request::create('/'),
        fn () => new Response('passed'),
        ...($ability === null ? [] : [$ability]),
    );

    return $response;
}

it('uses the dashboard gate when no ability is given', function () {
    Trail::auth(fn () => true);

    expect(runAuthorize(null)->getContent())->toBe('passed');
});

it('uses the mcp gate when the mcp ability is given', function () {
    Trail::auth(fn () => false);   // dashboard denies
    Trail::mcpUsing(fn () => true); // mcp allows

    expect(runAuthorize('mcp')->getContent())->toBe('passed');
});

it('aborts 403 when the mcp gate denies', function () {
    Trail::mcpUsing(fn () => false);

    expect(fn () => runAuthorize('mcp'))
        ->toThrow(HttpException::class);
});
