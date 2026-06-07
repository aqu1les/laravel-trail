<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Trail\Trail\Livewire\Events;
use Trail\Trail\Trail;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

it('passes demo=true from route defaults into a full-page component mount', function () {
    // Same mechanism the /trail/demo routes use. Demo mode seeds 50 events,
    // so the real-data empty state must NOT appear.
    Route::get('/__demo_probe', Events::class)->defaults('demo', true);

    $this->get('/__demo_probe')
        ->assertOk()
        ->assertDontSee('Nenhum evento corresponde');
});
