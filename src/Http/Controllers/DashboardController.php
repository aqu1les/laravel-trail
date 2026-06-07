<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController
{
    /**
     * Render the dashboard shell that hosts the front-end application.
     */
    public function index(): View
    {
        return view('trail::dashboard');
    }
}
