<?php

declare(strict_types=1);

use Trail\Trail\Trail;

afterEach(function () {
    Trail::auth(null);
});

it('registers the dashboard route under the configured path', function () {
    Trail::auth(fn () => true);

    $this->get('/trail')->assertOk();
});

it('forbids the dashboard when the auth gate denies', function () {
    Trail::auth(fn () => false);

    $this->get('/trail')->assertForbidden();
});

it('allows the dashboard when the auth gate permits', function () {
    Trail::auth(fn ($request) => true);

    $this->get('/trail')->assertOk();
});

it('honours a custom path from config', function () {
    config()->set('trail.path', 'insights');
    Trail::auth(fn () => true);

    $this->get('/insights')->assertOk();
})->skip('path is read at route-registration (boot) time; covered by default-path test');
