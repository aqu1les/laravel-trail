<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Trail\Trail\Http\Middleware\TrackPageView;
use Trail\Trail\TrailServiceProvider;

/**
 * Regression for the zapbot bug: on Laravel 11+, the HTTP kernel is the source of
 * truth for middleware groups. When anything mutates middleware after we boot, the
 * kernel re-syncs its groups onto the router via syncMiddlewareToRouter(), which
 * REPLACES each router group. A push made only to the router (the old behavior) is
 * therefore dropped, and page views silently stop being recorded even though
 * `auto_track.page_views` is true. Pushing onto the kernel group survives the sync.
 */
it('keeps TrackPageView wired after a later kernel resync', function () {
    config()->set('trail.auto_track.page_views', true);

    (new TrailServiceProvider(app()))->registerPageViewTracking();

    // Simulate a later middleware mutation re-syncing the kernel onto the router.
    $kernel = app(HttpKernel::class);
    $sync = new ReflectionMethod($kernel, 'syncMiddlewareToRouter');
    $sync->setAccessible(true);
    $sync->invoke($kernel);

    expect(app('router')->getMiddlewareGroups()['web'] ?? [])->toContain(TrackPageView::class);
});
