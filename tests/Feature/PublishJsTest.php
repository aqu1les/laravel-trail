<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Trail\Trail\TrailServiceProvider;

it('registers the trail-js publish tag pointing at the JS client', function () {
    $paths = ServiceProvider::pathsToPublish(TrailServiceProvider::class, 'trail-js');

    expect($paths)->not->toBeEmpty();

    $source = array_key_first($paths);
    expect($source)->toContain('resources/js/trail')
        ->and($paths[$source])->toContain('js/vendor/trail');
});
