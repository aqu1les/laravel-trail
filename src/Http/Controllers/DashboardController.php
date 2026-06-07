<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController
{
    /**
     * Overview — the dashboard landing screen.
     *
     * The data screens are server-rendered Blade today and ship with
     * representative sample data; wiring them to the JSON API is a
     * later pass owned by the data layer.
     */
    public function index(): View
    {
        return view('trail::overview', ['active' => 'overview']);
    }

    /**
     * Events Explorer — filterable stream with a payload drawer.
     */
    public function events(): View
    {
        return view('trail::events', ['active' => 'events']);
    }

    /**
     * Subject Timeline — every event of a single actor, by day.
     */
    public function timeline(): View
    {
        return view('trail::subject-timeline', ['active' => 'timeline']);
    }

    /**
     * Design System — the navigable token + component showcase.
     */
    public function designSystem(): View
    {
        return view('trail::design-system', ['active' => 'design-system']);
    }
}
