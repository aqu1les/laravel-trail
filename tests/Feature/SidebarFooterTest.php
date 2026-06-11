<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;
use Trail\Trail\TrailServiceProvider;

beforeEach(fn () => Trail::auth(fn () => true));
afterEach(fn () => Trail::auth(null));

it('shows the authenticated user name and initials in the footer', function () {
    $user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@acme.app']);
    $this->actingAs($user);

    $this->get('/trail')
        ->assertOk()
        ->assertSee('Ada Lovelace', false)
        ->assertSee('ada@acme.app', false)
        ->assertSee('>AL<', false);
});

it('omits the user block when no one is authenticated', function () {
    $this->get('/trail')
        ->assertOk()
        ->assertDontSee('<span class="trail-avatar">', false);
});

it('renders the back link when back_url is configured', function () {
    config()->set('trail.branding.back_url', '/admin');
    config()->set('trail.branding.back_label', 'Voltar ao admin');

    $this->get('/trail')
        ->assertOk()
        ->assertSee('href="/admin"', false)
        ->assertSee('Voltar ao admin', false);
});

it('omits the back link when back_url is null', function () {
    config()->set('trail.branding.back_url', null);

    $this->get('/trail')
        ->assertOk()
        ->assertDontSee('Voltar ao app', false);
});

it('renders a custom footer view when footer_view is overridden', function () {
    config()->set('trail.branding.footer_view', 'trail-fixtures::custom-footer');

    $this->get('/trail')
        ->assertOk()
        ->assertSee('FOOTER CUSTOMIZADO', false)
        ->assertDontSee('Tema escuro', false);
});

it('registers the footer view under the trail-views publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(
        TrailServiceProvider::class,
        'trail-views'
    );

    expect($paths)->not->toBeEmpty();
    expect(implode('|', array_values($paths)))->toContain('partials');
});
