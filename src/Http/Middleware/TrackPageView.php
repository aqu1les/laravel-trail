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

        if ($this->shouldTrack($request, $response)) {
            $properties = ['path' => $request->path()];

            if ($route = $request->route()?->getName()) {
                $properties = ['route' => $route] + $properties;
            }

            Trail::track(Trail::pageViewName(), $properties);
        }

        return $response;
    }

    protected function shouldTrack(Request $request, Response $response): bool
    {
        /** @var array<int, string> $ignore */
        $ignore = (array) config('trail.auto_track.ignore', []);

        // The ignore list is a hard guard, applied before any override. It is
        // what keeps the dashboard from recording page views of itself.
        if ($ignore !== [] && $request->is(...$ignore)) {
            return false;
        }

        return Trail::isNavigation($request, $response);
    }
}
