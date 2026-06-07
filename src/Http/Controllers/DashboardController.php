<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController
{
    /**
     * Render the dashboard. For now this serves the design-system
     * showcase — the navigable proof that the token layer and the
     * .trail-* component classes render correctly inside the package.
     * The data screens (Overview, Events, Timeline) land on top of
     * this foundation in a later pass.
     */
    public function index(): View
    {
        return view('trail::design-system');
    }
}
