<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Trail\Trail\Contracts\EventBuffer;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Tests\Fixtures\User;

beforeEach(function () {
    config()->set('trail.recorder', 'sync');
});

class FixedSubjectResolver
{
    public function __construct(private User $user) {}

    public function __invoke(): User
    {
        return $this->user;
    }
}

it('records a bare event synchronously', function () {
    Trail::track('product.viewed');

    $event = TrailEvent::firstWhere('name', 'product.viewed');

    expect($event)->not->toBeNull()
        ->and($event->subject_type)->toBeNull()
        ->and($event->subject_id)->toBeNull();
});

it('records properties and a value', function () {
    Trail::track('order.placed', ['order_id' => 7], value: 97.00);

    $event = TrailEvent::firstWhere('name', 'order.placed');

    expect($event->properties)->toBe(['order_id' => 7])
        ->and((float) $event->value)->toBe(97.00);
});

it('attributes the event to a subject via for()', function () {
    $user = User::create(['name' => 'Ada']);

    Trail::for($user)->track('team.invited', ['email' => 'a@b.c']);

    $event = TrailEvent::firstWhere('name', 'team.invited');

    expect($event->subject->is($user))->toBeTrue()
        ->and($event->properties)->toBe(['email' => 'a@b.c']);
});

it('records an anonymous event with no subject', function () {
    Trail::anonymous()->track('landing.cta_clicked');

    $event = TrailEvent::firstWhere('name', 'landing.cta_clicked');

    expect($event->subject_type)->toBeNull()
        ->and($event->subject_id)->toBeNull();
});

it('records session and context', function () {
    Trail::withSession('sess-123')
        ->withContext(['referrer' => 'google'])
        ->track('page.viewed');

    $event = TrailEvent::firstWhere('name', 'page.viewed');

    expect($event->session_id)->toBe('sess-123')
        ->and($event->context)->toMatchArray(['referrer' => 'google']);
});

it('does not leak fluent state between calls on the singleton', function () {
    $user = User::create(['name' => 'Ada']);

    Trail::for($user)->track('first.event');
    Trail::track('second.event');

    $second = TrailEvent::firstWhere('name', 'second.event');

    expect($second->subject_id)->toBeNull();
});

it('forces the sync recorder via sync() even when config differs', function () {
    config()->set('trail.recorder', 'queue');

    Trail::sync()->track('critical.event');

    expect(TrailEvent::firstWhere('name', 'critical.event'))->not->toBeNull();
});

it('ships a config that survives config:cache (no closures)', function () {
    $config = require __DIR__.'/../../config/trail.php';

    $hasClosure = function (array $values) use (&$hasClosure): bool {
        foreach ($values as $value) {
            if ($value instanceof Closure) {
                return true;
            }

            if (is_array($value) && $hasClosure($value)) {
                return true;
            }
        }

        return false;
    };

    expect($hasClosure($config))->toBeFalse();
});

it('attributes events to the authenticated user when no resolver is configured', function () {
    $user = User::create(['name' => 'Ada']);
    config()->set('trail.subject.resolver', null);
    $this->actingAs($user);

    Trail::track('dashboard.opened');

    expect(TrailEvent::firstWhere('name', 'dashboard.opened')->subject->is($user))->toBeTrue();
});

it('honors a callable resolver configured at runtime', function () {
    $user = User::create(['name' => 'Grace']);
    config()->set('trail.subject.resolver', fn () => $user);

    Trail::track('report.generated');

    expect(TrailEvent::firstWhere('name', 'report.generated')->subject->is($user))->toBeTrue();
});

it('resolves an invokable class-string resolver from the container', function () {
    $user = User::create(['name' => 'Linus']);
    config()->set('trail.subject.resolver', FixedSubjectResolver::class);
    app()->instance(FixedSubjectResolver::class, new FixedSubjectResolver($user));

    Trail::track('build.shipped');

    expect(TrailEvent::firstWhere('name', 'build.shipped')->subject->is($user))->toBeTrue();
});

it('records a custom occurred_at via at()', function () {
    $when = Carbon::parse('2026-01-02 03:04:05');

    Trail::anonymous()->at($when)->track('custom.time');

    expect(TrailEvent::firstWhere('name', 'custom.time')->occurred_at->toDateTimeString())
        ->toBe('2026-01-02 03:04:05');
});

it('falls back to now() when at() is given null', function () {
    Trail::anonymous()->at(null)->track('now.time');

    expect(TrailEvent::firstWhere('name', 'now.time')->occurred_at)->not->toBeNull();
});

it('routes through a named recorder via usingRecorder()', function () {
    config()->set('trail.ingest.buffer', 'memory');
    config()->set('trail.ingest.flush_at', 100);
    app()->forgetInstance(EventBuffer::class);

    Trail::anonymous()->usingRecorder('ingest')->track('buffered.event');

    expect(TrailEvent::count())->toBe(0);

    app(EventBuffer::class)->flush();

    expect(TrailEvent::firstWhere('name', 'buffered.event'))->not->toBeNull();
});

it('uses the global recorder when usingRecorder() gets null', function () {
    Trail::anonymous()->usingRecorder(null)->track('global.recorder');

    expect(TrailEvent::firstWhere('name', 'global.recorder'))->not->toBeNull();
});
