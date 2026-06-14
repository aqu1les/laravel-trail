<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Trail\Trail\Trail;

class Authorize
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $allowed = $ability === 'ingest'
            ? Trail::canIngest($request)
            : Trail::check($request);

        abort_unless($allowed, 403);

        return $next($request);
    }
}
