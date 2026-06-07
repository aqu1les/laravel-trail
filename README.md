<div align="center">

# Trail

**Know what your users actually do - without gluing together five services to find out.**

[![Packagist Version](https://img.shields.io/packagist/v/aqu1les/laravel-trail.svg?style=flat-square)](https://packagist.org/packages/aqu1les/laravel-trail)
![PHP](https://img.shields.io/badge/php-8.3%2B-777bb4?style=flat-square)
![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-ff2d20?style=flat-square)
[![License](https://img.shields.io/packagist/l/aqu1les/laravel-trail.svg?style=flat-square)](LICENSE.md)

</div>

---

Trail is event tracking for Laravel that lives **inside your app**. One line records an event. Events are tied to whoever (or whatever) did them - a user, a team, an anonymous visitor - and stored in your own database, on your own terms. There's a built-in dashboard so you don't have to build reporting from scratch.

No third-party pixel. No data leaving your servers. No "enterprise" upsell to see last week's numbers.

```php
Trail::track('order.placed', ['order_id' => $order->id], value: $order->total);
```

That's the whole API surface you need to get started. Everything else is sugar.

## Why this exists

Most analytics tools answer "how many" really well and "who, exactly, and what did they do right before churning" really badly. The data you'd want to answer the second question already lives in your database - it's just never written down. Trail writes it down, in a shape you can query with plain Eloquent, and keeps the actor polymorphic so it works whether your "user" is a `User`, a `Team`, or a `Customer`.

- **Convention over configuration.** Works out of the box with `App\Models\User`. Everything is overridable.
- **Polymorphic by design.** Track any model, not just users.
- **Your database, your rules.** Uses your default connection. Point it elsewhere if you want.
- **Three recording modes.** Synchronous, queued, or batched - pick your latency/throughput trade-off.
- **Privacy first.** IPs are anonymized by default and event *content* is never stored - only metadata.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require aqu1les/laravel-trail
php artisan migrate
```

That's the whole install. Migrations are auto-loaded, so `migrate` just works - no publish step. The service provider also auto-registers the routes, the facade, and the `trail:aggregate`/`trail:prune` schedule.

For a guided setup - publishes the config and agent skill, and scaffolds the dashboard gate at `app/Providers/TrailServiceProvider.php`:

```bash
php artisan trail:install
```

Want to own the config or migrations? Publish them explicitly:

```bash
php artisan vendor:publish --tag="trail-config"
php artisan vendor:publish --tag="trail-migrations"  # then these run instead of the bundled ones
```

### Installing from the Git repo (not on Packagist yet)

Trail isn't published to Packagist. Point Composer at the repository from your app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/aqu1les/laravel-trail" }
]
```

Then require the branch you want:

```bash
composer require aqu1les/laravel-trail:dev-main
```

**Developing the package locally?** Use a `path` repository so your app symlinks your working copy and picks up edits with no reinstall:

```json
"repositories": [
    { "type": "path", "url": "../laravel-trail" }
]
```

```bash
composer require aqu1les/laravel-trail:@dev
```

The `dev-main`/`@dev` constraints already opt into dev stability for this one package. If Composer still refuses, add `"minimum-stability": "dev"` and `"prefer-stable": true` to your app's `composer.json`.

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

**3. Read it back** - through Eloquent, or the JSON API at `/trail/api/*`.

```php
$user->trailEvents()->latest('occurred_at')->get();
```

## The `track` API

The facade reads like a sentence. You start broad and narrow down with fluent methods, then call `track()`.

```php
// Bare event - actor resolved from the configured resolver (auth user by default)
Trail::track('dashboard.opened');

// With properties and a numeric value (revenue, duration, score…)
Trail::track('subscription.created', ['plan' => 'pro'], value: 97.00);

// Attribute to a specific actor - any model works
Trail::for($team)->track('member.invited', ['email' => $email]);

// No actor at all - e.g. a visitor on your landing page
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

When you call `Trail::track()` without `for()`, Trail figures out the actor from the resolver in your config - `auth()->user()` by default. Swap it for anything:

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

- **`sync`** - writes during the request. Simplest, adds a little latency. Great for local dev and events you can't afford to lose.
- **`queue`** - dispatches a job and moves on. Negligible request overhead, needs a worker running. The recommended default.
- **`ingest`** - buffers events and flushes them in a single bulk insert (Pulse-style) for high volume. Buffer is `redis` (accumulates across requests) or `memory`, via `trail.ingest.buffer`.

> The default `queue` (and `ingest`'s `redis` buffer) keep tracking off the request's critical path, but expect infrastructure - a queue worker, and Redis for `ingest`. `sync` needs neither but adds latency to every tracked request. See **Going to production** below.

Recorders go through a Laravel `Manager`, so adding your own is a one-liner:

```php
app(\Trail\Trail\RecorderManager::class)->extend('bigquery', fn ($app) => new BigQueryRecorder());
```

## The dashboard

Trail ships an embedded dashboard at `/trail` (configurable via `trail.path`), in the spirit of Pulse and Telescope - dense, dark by default, server-rendered with Blade + Livewire. No build step and no `npm`: the styles are plain CSS driven by design tokens.

Three screens are built today, on your real data:

- **Overview** (`/trail`) - totals, unique actors, a time series with hour/day/week granularity, top events and most-active actors.
- **Events** (`/trail/events`) - a filterable, live-updating stream with a payload drawer (properties, context, raw JSON).
- **Subject Timeline** (`/trail/timeline`) - every event of a single actor, grouped by day, with inline payload expansion.

There's also a **Design System** page at `/trail/design-system`, and - in your `local` environment only - **demo** versions of every screen under `/trail/demo/*`, so you can preview the UI with sample data before any real events land. Funnels is the one screen still on the roadmap.

### JSON API

The same data is exposed as JSON, handy for your own tooling:

| Endpoint | Returns |
| --- | --- |
| `GET /trail/api/events` | Paginated events, newest first. Filter by `name`, `subject_type`, `subject_id`, `session_id`, `from`, `to`, `order` |
| `GET /trail/api/metrics` | Totals, unique actors, top events, and a per-bucket time series (`period`) |
| `GET /trail/api/funnel?steps[]=a&steps[]=b` | Conversion through an ordered sequence - count, rate, drop-off per step |

In code (and inside the Livewire screens), the same data is available through fluent read helpers:

```php
Trail::events()->for($user)->between($start, $end)->paginate(25);
Trail::count('order.placed')->today();
Trail::funnel(['signup', 'activated', 'purchase']);
```

### Styling - zero config

The dashboard ships its own pre-compiled stylesheet (`dist/trail.css`) and injects it inline, the same way Laravel Pulse does. There is **nothing to configure**: install the package and `/trail` is fully styled in every environment, production included. No Tailwind CDN, no `@source` pointing at `vendor/`, no build step on your side.

If you ever change the bundled CSS source while developing the package, recompile it with `bun run build` (or `bun run dev` to watch).

### Theming

Every colour, font, radius and spacing in the dashboard is a `--trail-*` CSS variable. Neutrals are zinc, the accent is rose, dark is the default (`.dark` on `<html>`) with a working light theme.

To rebrand it, compile your own copy of the stylesheet and point Trail at it. Publish the source, override any token after the import, then set `trail.stylesheet`:

```bash
php artisan vendor:publish --tag="trail-styles"
```

```css
/* resources/css/app.css */
@import "tailwindcss";
@source "../../vendor/aqu1les/laravel-trail/resources/views";  /* the utilities the dashboard uses */
@import "trail/styles.css";                                     /* tokens + components */

:root {
    --trail-accent: #16a34a;        /* swap the rose accent for your brand */
    --trail-radius-lg: 6px;
    --trail-font-sans: "Geist", sans-serif;
}
```

```php
// config/trail.php  (or TRAIL_DASHBOARD_CSS)
'stylesheet' => '/build/assets/app.css', // your compiled file, e.g. Vite::asset('resources/css/app.css')
```

When `trail.stylesheet` is set, the dashboard loads it instead of the bundled inline stylesheet. With a Tailwind v4 build the tokens are also exposed as utilities (`bg-surface`, `text-muted`, `text-accent`, `border-border`, `font-mono`) through `@theme`.

### Locking it down

The dashboard is behind a gate. By default it only opens in your `local` environment - define who else gets in from a service provider:

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

Because the config is published into your app, you override values by editing the file - no environment variables required for things like the subject model.

## Going to production

Recording works the moment you install and add the trait. Two things still need *your* infrastructure, because the defaults favour performance over zero-setup:

1. **Run a queue worker** - the default recorder is `queue`, so events sit in the queue until a worker handles them: `php artisan queue:work`. (Or set `TRAIL_RECORDER=sync`, accepting per-request latency; or `ingest` for batching.)
2. **Provision Redis if you use `ingest`** - its default buffer is `redis`; otherwise set `TRAIL_INGEST_BUFFER=memory`.
3. **Protect the dashboard** - it's `local`-only by default. `trail:install` scaffolds `app/Providers/TrailServiceProvider.php` where you define `Trail::auth(...)` (see above).

Maintenance is handled for you: `trail:aggregate` (hourly) and `trail:prune` (daily) are **auto-registered on the scheduler** - just make sure the scheduler itself runs (`* * * * * php artisan schedule:run`). Turn either off via `config('trail.schedule')`. Both are also plain Artisan commands (`trail:aggregate`, `trail:prune`, `trail:install`) if you prefer to schedule them yourself.

## Privacy

This is deliberate, not an afterthought:

- IP addresses are anonymized by default, and not stored at all unless you opt in.
- Trail records event **metadata** - names, properties you choose to pass, timestamps. It never stores message bodies or page content. What ends up in `properties` is whatever *you* put there, so keep PII out of it.

## Roadmap

Shipped:

- ✅ `track()` with sync, queue and ingest recorders (pluggable Redis/memory buffer)
- ✅ Polymorphic actor + `HasTrail` trait, configurable resolver
- ✅ Fluent read helpers - `Trail::events()`, `Trail::count()`, `Trail::funnel()`
- ✅ Pre-computed aggregates + `trail:aggregate`, `trail:prune`, `trail:install` commands
- ✅ Privacy-aware context capture + opt-in page-view tracking
- ✅ Auto-registered routes, JSON API, dashboard auth gate
- ✅ Embedded Blade + Livewire dashboard - Overview, Events, Subject Timeline, design-system showcase, token theming

Next up:

- ⏳ Funnels screen
- ⏳ Pluggable storage drivers (ClickHouse) for very high volume

## Testing

```bash
composer test
```

The suite runs on Pest with Testbench against an in-memory SQLite database.

## Contributing

Pull requests are welcome. Please keep the existing style - run `vendor/bin/pint` before pushing, and add tests for anything you change.

## Credits

- [Felipe Barros](https://github.com/aqu1les)
- [All Contributors](../../contributors)

## License

MIT. See [LICENSE.md](LICENSE.md).
