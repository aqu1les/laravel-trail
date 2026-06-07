<?php

declare(strict_types=1);

use Trail\Trail\Http\Middleware\TrackPageView;
use Trail\Trail\TrailServiceProvider;

function webGroup(): array
{
    return app('router')->getMiddlewareGroups()['web'] ?? [];
}

it('registers TrackPageView on the web group when page_views is enabled', function () {
    config()->set('trail.auto_track.page_views', true);

    (new TrailServiceProvider(app()))->registerPageViewTracking();

    expect(webGroup())->toContain(TrackPageView::class);
});

it('does not register TrackPageView when page_views is disabled', function () {
    config()->set('trail.auto_track.page_views', false);

    (new TrailServiceProvider(app()))->registerPageViewTracking();

    expect(webGroup())->not->toContain(TrackPageView::class);
});
