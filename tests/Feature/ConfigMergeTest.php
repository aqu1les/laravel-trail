<?php

declare(strict_types=1);

use Trail\Trail\Support\ConfigMerge;

it('fills nested keys missing from the user config with package defaults', function () {
    $package = ['auto_track' => ['page_views' => false, 'event_name' => 'page.viewed']];
    $user = ['auto_track' => ['page_views' => true]];

    $merged = ConfigMerge::merge($package, $user);

    expect($merged['auto_track']['page_views'])->toBeTrue()
        ->and($merged['auto_track']['event_name'])->toBe('page.viewed');
});

it('lets user values win over package defaults', function () {
    $package = ['auto_track' => ['event_name' => 'page.viewed']];
    $user = ['auto_track' => ['event_name' => 'visit.tracked']];

    expect(ConfigMerge::merge($package, $user)['auto_track']['event_name'])->toBe('visit.tracked');
});

it('replaces lists wholesale instead of concatenating them', function () {
    $package = ['auto_track' => ['ignore' => ['trail*', 'horizon*', 'telescope*']]];
    $user = ['auto_track' => ['ignore' => ['custom*']]];

    expect(ConfigMerge::merge($package, $user)['auto_track']['ignore'])->toBe(['custom*']);
});

it('keeps keys that only exist in the user config', function () {
    $package = ['recorder' => 'queue'];
    $user = ['recorder' => 'sync', 'custom' => 'x'];

    $merged = ConfigMerge::merge($package, $user);

    expect($merged['recorder'])->toBe('sync')->and($merged['custom'])->toBe('x');
});
