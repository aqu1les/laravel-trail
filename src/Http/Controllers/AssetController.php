<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers;

use Illuminate\Http\Response;

class AssetController
{
    /**
     * The design-system stylesheets, concatenated in cascade order.
     *
     * The dashboard ships its CSS through this route so the embedded
     * panel renders on a fresh install with zero `vendor:publish`
     * (the Pulse/Telescope pattern). Consumers running a Tailwind v4
     * build instead `@import "trail/styles.css"` from the published
     * source and never hit this endpoint.
     *
     * @var list<string>
     */
    private const SHEETS = [
        'tokens/colors.css',
        'tokens/typography.css',
        'tokens/foundations.css',
        'components.css',
        // tokens/theme.css is the Tailwind `@theme` bridge - only
        // meaningful inside a real Tailwind build, ignored here.
    ];

    /**
     * Serve the concatenated design-system stylesheet.
     */
    public function css(): Response
    {
        $base = __DIR__.'/../../../resources/css/trail/';

        $css = '';

        foreach (self::SHEETS as $sheet) {
            $contents = (string) file_get_contents($base.$sheet);

            // `@import` statements are stripped: relative token imports are
            // already inlined by concatenation, and the Google Fonts import
            // is loaded via a <link> in the layout head instead.
            $contents = (string) preg_replace('/^\s*@import\b.*$/m', '', $contents);

            $css .= $contents."\n";
        }

        return $this->respond($css);
    }

    /**
     * Build a cacheable text/css response.
     */
    private function respond(string $css): Response
    {
        $expires = now()->addYear();

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Expires' => $expires->toRfc7231String(),
        ]);
    }
}
