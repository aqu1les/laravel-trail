<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides whether a request is a Navigation: the document load or Inertia
 * visit that changes the page the actor is looking at.
 *
 * See CONTEXT.md for the term and docs/adr/0001 for why this reads request
 * headers instead of the response.
 */
class NavigationDetector
{
    /**
     * Inertia headers that mark a request as a partial reload rather than a
     * visit: refreshing props of the page the actor is already on. Covers
     * deferred props and infinite scroll too.
     */
    protected const INERTIA_PARTIAL_HEADERS = [
        'X-Inertia-Partial-Data',
        'X-Inertia-Partial-Except',
        'X-Inertia-Partial-Component',
    ];

    /**
     * The $response is unused by this rule, which is entirely request-side.
     * It stays in the signature so this class and an override closure
     * registered via Trail::trackPageViewsUsing() are interchangeable.
     */
    public function isNavigation(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        // Inertia and Livewire navigate by XHR, so they carry
        // Sec-Fetch-Dest: empty and must be resolved before that check.
        if ($request->headers->has('X-Inertia')) {
            return $this->isInertiaNavigation($request);
        }

        if ($request->headers->has('X-Livewire-Navigate')) {
            // Livewire's wire:navigate.hover prefetch is indistinguishable from
            // a real navigation: both go through sendNavigateRequest() with the
            // same headers. Documented as a known limitation.
            return true;
        }

        if ($request->headers->has('Sec-Fetch-Dest')) {
            if ($request->headers->get('Sec-Fetch-Dest') !== 'document') {
                return false;
            }

            // Chrome speculation rules (prefetch/prerender) and <link rel="prefetch">
            // send Sec-Fetch-Dest: document for a page the actor may never open.
            // Sec-Purpose carries "prefetch" or "prefetch;prerender"; older clients
            // send the legacy Purpose: prefetch instead.
            if (str_contains((string) $request->headers->get('Sec-Purpose'), 'prefetch')) {
                return false;
            }

            return $request->headers->get('Purpose') !== 'prefetch';
        }

        // No Fetch Metadata at all: a pre-2023 browser, or a non-browser client.
        // Accept is the signal that predates it. fetch() defaults to */*.
        return str_contains((string) $request->headers->get('Accept'), 'text/html');
    }

    protected function isInertiaNavigation(Request $request): bool
    {
        foreach (self::INERTIA_PARTIAL_HEADERS as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        // router.reload() sets Cache-Control: no-cache, and router.poll()
        // is implemented on top of reload().
        if (str_contains((string) $request->headers->get('Cache-Control'), 'no-cache')) {
            return false;
        }

        return $request->headers->get('Purpose') !== 'prefetch';
    }
}
