<?php

declare(strict_types=1);

use Trail\Trail\Trail;

afterEach(function () {
    Trail::auth(null);
    Trail::ingestUsing(null);
});

it('allows ingest by default, including anonymous', function () {
    expect(Trail::canIngest(request()))->toBeTrue();
});

it('honors a custom ingest gate', function () {
    Trail::ingestUsing(fn () => false);

    expect(Trail::canIngest(request()))->toBeFalse();
});

it('keeps the view gate independent from the ingest gate', function () {
    Trail::auth(fn () => false);
    Trail::ingestUsing(fn () => true);

    expect(Trail::check(request()))->toBeFalse()
        ->and(Trail::canIngest(request()))->toBeTrue();
});
