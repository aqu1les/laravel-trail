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
        abort_unless($this->allows($ability, $request), 403);

        return $next($request);
    }

    private function allows(string $ability, Request $request): bool
    {
        return match ($ability) {
            'ingest' => Trail::canIngest($request),
            'mcp' => Trail::canAccessMcp($request),
            default => Trail::check($request),
        };
    }
}
