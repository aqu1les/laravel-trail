<?php

declare(strict_types=1);

use Trail\Trail\Trail;

afterEach(function () {
    Trail::auth(null);
});

it('Trail::styles() returns an inline tag carrying the design-system tokens', function () {
    $css = Trail::styles();

    expect($css)
        ->toStartWith('<style>')
        ->toEndWith('</style>')
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
