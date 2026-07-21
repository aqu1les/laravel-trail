<?php

declare(strict_types=1);

use Trail\Trail\Queries\SubjectIdentity;
use Trail\Trail\Queries\SubjectKey;
use Trail\Trail\Tests\Fixtures\User;

it('prefers the name whenever there is one', function () {
    $user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@acme.app']);
    $key = SubjectKey::of(User::class, $user->id);
    $identities = SubjectIdentity::resolve([$key]);

    expect(SubjectIdentity::display($key, $identities)['name'])->toBe('Ada Lovelace')
        ->and(SubjectIdentity::display($key, $identities, emailAsName: true)['name'])->toBe('Ada Lovelace');
});

it('falls back to the id, or to the email only when asked', function () {
    // The screens differ on purpose: the Events actor menu shows the address,
    // the timeline's list views keep it off and show the id instead.
    $user = User::create(['email' => 'ada@acme.app']);
    $key = SubjectKey::of(User::class, $user->id);
    $identities = SubjectIdentity::resolve([$key]);

    expect(SubjectIdentity::display($key, $identities)['name'])->toBe('User #'.$user->id)
        ->and(SubjectIdentity::display($key, $identities, emailAsName: true)['name'])->toBe('ada@acme.app')
        ->and(SubjectIdentity::display($key, $identities)['email'])->toBe('ada@acme.app');
});

it('falls back to the id when the subject cannot be resolved at all', function () {
    $key = SubjectKey::of(User::class, 999);

    expect(SubjectIdentity::display($key, [], emailAsName: true)['name'])->toBe('User #999');
});
