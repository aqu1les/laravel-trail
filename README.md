<div align="center">

# Trail

**Know what your users actually do — without gluing together five services to find out.**

[![Packagist Version](https://img.shields.io/packagist/v/aqu1les/laravel-trail.svg?style=flat-square)](https://packagist.org/packages/aqu1les/laravel-trail)
![PHP](https://img.shields.io/badge/php-8.3%2B-777bb4?style=flat-square)
![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20?style=flat-square)
[![License](https://img.shields.io/packagist/l/aqu1les/laravel-trail.svg?style=flat-square)](LICENSE.md)

</div>

---

Trail is event tracking for Laravel that lives **inside your app**. One line records an event. Events are tied to whoever (or whatever) did them — a user, a team, an anonymous visitor — and stored in your own database, on your own terms. There's a built-in dashboard so you don't have to build reporting from scratch.

No third-party pixel. No data leaving your servers. No "enterprise" upsell to see last week's numbers.

```php
Trail::track('order.placed', ['order_id' => $order->id], value: $order->total);
```

That's the whole API surface you need to get started. Everything else is sugar.

## Why this exists

Most analytics tools answer "how many" really well and "who, exactly, and what did they do right before churning" really badly. The data you'd want to answer the second question already lives in your database — it's just never written down. Trail writes it down, in a shape you can query with plain Eloquent, and keeps the actor polymorphic so it works whether your "user" is a `User`, a `Team`, or a `Customer`.

- **Convention over configuration.** Works out of the box with `App\Models\User`. Everything is overridable.
- **Polymorphic by design.** Track any model, not just users.
- **Your database, your rules.** Uses your default connection. Point it elsewhere if you want.
- **Three recording modes.** Synchronous, queued, or batched — pick your latency/throughput trade-off.
- **Privacy first.** IPs are anonymized by default and event *content* is never stored — only metadata.

## Requirements

- PHP 8.3+
- Laravel 11, 12 or 13

## Installation

```bash
composer require aqu1les/laravel-trail
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="trail-migrations"
php artisan migrate
```

Optionally publish the config to tune things:

```bash
php artisan vendor:publish --tag="trail-config"
```

That's it. The service provider auto-registers itself, the routes, and the facade.

## Quick start

**1. Add the trait to whatever you consider your "actor".**

```php
use Trail\Trail\Concerns\HasTrail;

class User extends Authenticatable
{
    use HasTrail;
}
```

**2. Track something.**

```php
use Trail\Trail\Facades\Trail;

// Attributed to the authenticated user automatically
Trail::track('product.viewed', ['sku' => $product->sku]);

// Or straight from the model
$user->track('profile.updated', ['field' => 'avatar']);
```

**3. Read it back** — through Eloquent, or the JSON API at `/trail/api/*`.

```php
$user->trailEvents()->latest('occurred_at')->get();
```

## The `track` API

The facade reads like a sentence. You start broad and narrow down with fluent methods, then call `track()`.

```php
// Bare event — actor resolved from the configured resolver (auth user by default)
Trail::track('dashboard.opened');

// With properties and a numeric value (revenue, duration, score…)
Trail::track('subscription.created', ['plan' => 'pro'], value: 97.00);

// Attribute to a specific actor — any model works
Trail::for($team)->track('member.invited', ['email' => $email]);

// No actor at all — e.g. a visitor on your landing page
Trail::anonymous()->track('landing.cta_clicked');

// Pin a session or attach extra context
Trail::withSession($sessionId)
    ->withContext(['referrer' => 'newsletter'])
    ->track('signup.started');

// Force how this one event is written, ignoring the global default
Trail::sync()->track('payment.captured');
```

| Method | What it does |
| --- | --- |
| `for(Model $subject)` | Attribute the event to a specific actor |
| `anonymous()` | Record with no actor |
| `withSession(string $id)` | Group events under a session id |
| `withContext(array $context)` | Attach arbitrary context |
| `sync()` / `queue()` / `ingest()` | Override the recording mode for this call |
| `track(string $name, array $properties = [], ?float $value = null)` | Write the event |

A naming convention pays off fast: `domain.action` (`order.placed`, `onboarding.step_completed`). It keeps your event list readable and your funnels easy to build.

## The actor is polymorphic

The `HasTrail` trait gives any model two things:

```php
$model->trailEvents();                 // MorphMany relation back to its events
$model->track('did.something', [...]); // shortcut for Trail::for($model)->track(...)
```

When you call `Trail::track()` without `for()`, Trail figures out the actor from the resolver in your config — `auth()->user()` by default. Swap it for anything:

```php
// config/trail.php
'subject' => [
    'resolver' => fn () => auth('api')->user(),
    'model'    => App\Models\Customer::class,
],
```

Laravel's `morphMap` is respected, so your `subject_type` column stays clean.

## Recording modes

Set the default in config and override per-call when it matters.

```php
'recorder' => env('TRAIL_RECORDER', 'queue'), // sync | queue | ingest
```

- **`sync`** — writes during the request. Simplest, adds a little latency. Great for local dev and events you can't afford to lose.
- **`queue`** — dispatches a job and moves on. Negligible request overhead, needs a worker running. The recommended default.
- **`ingest`** — buffers events and flushes in batches (Pulse-style) for high volume. *On the roadmap — see below.*

Recorders go through a Laravel `Manager`, so adding your own is a one-liner:

```php
app(\Trail\Trail\RecorderManager::class)->extend('bigquery', fn ($app) => new BigQueryRecorder());
```

## The dashboard

Trail auto-registers its routes under `/trail` (configurable via `trail.path`). The JSON API is live today:

| Endpoint | Returns |
| --- | --- |
| `GET /trail/api/events` | Paginated events, newest first. Filter by `name`, `subject_type`, `subject_id`, `session_id`, `from`, `to` |
| `GET /trail/api/metrics` | Totals, unique actors, top events |
| `GET /trail/api/funnel?steps[]=a&steps[]=b` | Conversion through an ordered sequence of events |

### Locking it down

The dashboard is behind a gate. By default it only opens in your `local` environment — define who else gets in from a service provider:

```php
use Trail\Trail\Trail;

Trail::auth(fn ($request) => $request->user()?->isAdmin() ?? false);
```

Same idea as Horizon and Telescope. If the gate says no, the request gets a 403.

## Configuration

A few of the knobs in `config/trail.php`:

```php
'connection' => env('TRAIL_DB_CONNECTION'),     // null = your default connection
'recorder'   => env('TRAIL_RECORDER', 'queue'), // sync | queue | ingest
'path'       => env('TRAIL_PATH', 'trail'),     // dashboard URL prefix
'middleware' => ['web'],

'privacy' => [
    'anonymize_ip'     => true,  // on by default
    'store_ip'         => false,
    'store_user_agent' => true,
],

'retention' => [
    'events_days'     => env('TRAIL_RETENTION_DAYS', 90),
    'aggregates_days' => 730,
],
```

Because the config is published into your app, you override values by editing the file — no environment variables required for things like the subject model.

## Privacy

This is deliberate, not an afterthought:

- IP addresses are anonymized by default, and not stored at all unless you opt in.
- Trail records event **metadata** — names, properties you choose to pass, timestamps. It never stores message bodies or page content. What ends up in `properties` is whatever *you* put there, so keep PII out of it.

## Roadmap

Shipped:

- ✅ `track()` with sync + queue recorders
- ✅ Polymorphic actor + `HasTrail` trait
- ✅ Auto-registered routes, JSON API, dashboard auth gate

Next up:

- ⏳ `ingest` recorder (in-memory / Redis buffer with batched flush)
- ⏳ Pre-computed aggregates + `trail:aggregate`, `trail:prune`, `trail:install` commands
- ⏳ Automatic context capture (IP/UA per the privacy config) and opt-in page-view tracking
- ⏳ The visual dashboard (Overview, Events explorer, Funnels, Subject timeline)
- ⏳ Pluggable storage drivers (ClickHouse) for very high volume

## Testing

```bash
composer test
```

The suite runs on Pest with Testbench against an in-memory SQLite database.

## Contributing

Pull requests are welcome. Please keep the existing style — run `vendor/bin/pint` before pushing, and add tests for anything you change.

## Credits

- [Felipe Barros](https://github.com/aqu1les)
- [All Contributors](../../contributors)

## License

MIT. See [LICENSE.md](LICENSE.md).
