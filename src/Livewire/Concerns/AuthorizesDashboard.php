<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire\Concerns;

use Trail\Trail\Trail;

/**
 * Re-run the dashboard view gate on every Livewire request.
 *
 * The dashboard routes sit behind the Authorize middleware, but a component's
 * later updates go to Livewire's own endpoint, which never passes through
 * those routes. Livewire calls boot{Trait}() on the initial render and on
 * every update, so a snapshot issued to an admin cannot be replayed once the
 * gate stops allowing the request.
 */
trait AuthorizesDashboard
{
    public function bootAuthorizesDashboard(): void
    {
        abort_unless(Trail::check(request()), 403);
    }
}
