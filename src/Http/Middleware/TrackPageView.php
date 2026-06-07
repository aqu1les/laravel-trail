<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Trail\Trail\Facades\Trail;

class TrackPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request)) {
            Trail::withContext(['route' => $request->route()?->getName()])->track('page.viewed');
        }

        return $response;
    }

    protected function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        /** @var array<int, string> $ignore */
        $ignore = (array) config('trail.auto_track.ignore', []);

        return ! $request->is(...$ignore);
    }
}
