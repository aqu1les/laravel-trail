<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Trail\Trail\TrailServiceProvider;

it('registers the agent skill under the trail-skill publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(TrailServiceProvider::class, 'trail-skill');

    expect($paths)->not->toBeEmpty()
        ->and(array_values($paths)[0])->toContain('.claude/skills/trail/SKILL.md');
});

it('ships the skill source file with valid frontmatter', function () {
    $source = array_keys(
        ServiceProvider::pathsToPublish(TrailServiceProvider::class, 'trail-skill')
    )[0];

    expect(file_exists($source))->toBeTrue();

    $contents = (string) file_get_contents($source);
    expect($contents)->toContain('name: trail')
        ->and($contents)->toContain('description:');
});
