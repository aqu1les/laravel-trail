<?php

declare(strict_types=1);

use Trail\Trail\Trail;

afterEach(function () {
    Trail::auth(null);
});

it('Trail::styles() returns an inline style tag', function () {
    $html = Trail::styles();

    expect($html)
        ->toStartWith('<style>')
        ->toEndWith('</style>');
});

it('Trail::styles() includes design-system tokens and component classes', function () {
    $css = Trail::styles();

    expect($css)
        ->toContain('--trail-accent')
        ->toContain('--trail-radius-lg')
        ->toContain('.trail-btn')
        ->toContain('.trail-card');
});

it('Trail::styles() contains no external @import statements', function () {
    $css = Trail::styles();

    // Google Fonts is loaded via <link> in the layout head - not in the compiled CSS.
    expect($css)->not->toContain('@import url(');
});
