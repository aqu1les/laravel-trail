<?php

declare(strict_types=1);

use Trail\Trail\Trail;

beforeEach(function () {
    Trail::auth(fn () => true);
});

afterEach(function () {
    Trail::auth(null);
});

it('renders the Overview screen at the dashboard root', function () {
    $this->get('/trail')
        ->assertOk()
        ->assertSee('Overview', false)
        ->assertSee('Eventos ao longo do tempo', false);
});

it('renders the Events Explorer screen', function () {
    $this->get('/trail/events')
        ->assertOk()
        ->assertSee('id="liveBtn"', false)
        ->assertSee('Buscar evento, ator, propriedade', false);
});

it('renders the Subject Timeline screen', function () {
    $this->get('/trail/timeline')
        ->assertOk()
        ->assertSee('Subject Timeline', false)
        ->assertSee('id="profile"', false);
});

it('renders the Design System showcase', function () {
    $this->get('/trail/design-system')
        ->assertOk()
        ->assertSee('O sistema visual do Trail', false);
});

it('links every screen through the shared sidebar', function () {
    $html = $this->get('/trail')->getContent();

    expect($html)
        ->toContain(route('trail.events'))
        ->toContain(route('trail.timeline'))
        ->toContain(route('trail.design-system'))
        ->toContain('/trail/trail.css'); // design-system stylesheet wired in
});
