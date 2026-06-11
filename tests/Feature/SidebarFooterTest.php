<?php

declare(strict_types=1);

use Trail\Trail\Tests\Fixtures\User;
use Trail\Trail\Trail;

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
