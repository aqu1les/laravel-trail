<?php

declare(strict_types=1);

use Trail\Trail\Trail;

afterEach(function () {
    Trail::auth(null);
});

it('serves the design-system stylesheet as text/css', function () {
    Trail::auth(fn () => true);

    $response = $this->get('/trail/trail.css');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/css');
});

it('inlines the token layer and component classes', function () {
    Trail::auth(fn () => true);

    $css = $this->get('/trail/trail.css')->getContent();

    // Tokens (colors + foundations) and component classes are concatenated.
    expect($css)
        ->toContain('--trail-accent')
        ->toContain('--trail-radius-lg')
        ->toContain('.trail-btn')
        ->toContain('.trail-card');
});

it('strips @import statements from the served stylesheet', function () {
    Trail::auth(fn () => true);

    $css = $this->get('/trail/trail.css')->getContent();

    // The Google Fonts @import is loaded via <link> in the layout instead.
    expect($css)->not->toContain('@import');
});
