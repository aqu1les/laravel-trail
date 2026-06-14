<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Trail\Trail\Mcp\Dashboard\Support\Provenance;

it('stamps an events footer with freshness', function () {
    Carbon::setTestNow('2026-06-14T12:00:00Z');

    $footer = Provenance::events();

    expect($footer)->toMatchArray([
        'source' => 'events',
        'truncated' => false,
    ]);
    expect($footer['as_of'])->toBe('2026-06-14T12:00:00+00:00');

    Carbon::setTestNow();
});

it('marks truncation with the applied limit', function () {
    $footer = Provenance::events(truncated: true, limit: 200);

    expect($footer['truncated'])->toBeTrue();
    expect($footer['limit'])->toBe(200);
});

it('stamps an aggregates footer with the last aggregation time', function () {
    $footer = Provenance::aggregates(Carbon::parse('2026-06-10T00:00:00Z'));

    expect($footer)->toMatchArray(['source' => 'aggregates']);
    expect($footer['last_aggregated_at'])->toBe('2026-06-10T00:00:00+00:00');
});
