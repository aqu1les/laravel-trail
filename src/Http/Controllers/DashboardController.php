<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController
{
    /**
     * Design System - the navigable token + component showcase.
     *
     * The data screens (Overview, Events, Subject Timeline) are full-page
     * Livewire components wired directly in routes/web.php.
     */
    public function designSystem(): View
    {
        return view('trail::design-system', ['active' => 'design-system']);
    }
}
