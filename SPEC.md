# Trail - Specification

> Repository reference document. Describes what Trail is, every requirement, and the current
> implementation status. For a usage-oriented guide see [README.md](README.md).

**Package:** `aqu1les/laravel-trail` · **Namespace:** `Trail\Trail\` · **License:** MIT
**Runtime:** PHP 8.3+, Laravel 11 / 12 / 13, `livewire/livewire` ^4.3

Status legend: ✅ shipped · 🚧 partial · ⏳ planned

---

## 1. Purpose

Trail is a publishable Laravel package for **user-behavior tracking**: recording events, journeys,
and actions over time. You install it via Composer, run one migration, and you are tracking and
visualizing behaviour - without building any reporting yourself, and without any data leaving your
servers.

One line records an event:

```php
Trail::track('order.placed', ['order_id' => $order->id], value: $order->total);
```

## 2. Design principles

- **Convention over configuration** - works out of the box with `App\Models\User`; everything is
  overridable through the published config.
- **Polymorphic actor** - the tracked subject is a `morphTo`; default `App\Models\User`, but any
  model (Team, Company, Customer) works with no schema change.
- **Your database, your rules** - stored on the app's default connection; configurable to another.
- **Configurable write mode** - synchronous, queued, or batched ingest.
- **Embedded dashboard** - server-rendered with Blade + Livewire, protected by a gate.
- **Privacy first** - IP anonymized by default; only event *metadata* is stored, never content.

## 3. Requirements

| # | Requirement | Status |
|---|---|---|
| R1 | Facade `Trail::track(name, props, value)` as the primary API | ✅ |
| R2 | Fluent builder: `for()`, `anonymous()`, `withSession()`, `withContext()`, `sync()/queue()/ingest()` | ✅ |
| R3 | Polymorphic actor (`morphTo`), default resolved from config | ✅ |
| R4 | `HasTrail` trait: `trailEvents()` relation + `track()` shortcut | ✅ |
| R5 | Three recorders: `sync`, `queue`, `ingest` | ✅ |
| R6 | Storage on default connection, configurable | ✅ |
| R7 | Auto-registered routes (configurable prefix + middleware) | ✅ |
| R8 | Dashboard gate via `Trail::auth(closure)` + `Authorize` middleware | ✅ |
| R9 | JSON API: events, metrics, funnel | ✅ |
| R10 | Fluent read helpers: `Trail::events()`, `Trail::count()`, `Trail::funnel()` | ✅ |
| R11 | Privacy-aware context capture (IP anonymize/omit, UA optional) | ✅ |
| R12 | Pre-computed aggregates + `trail:aggregate` | ✅ |
| R13 | Retention pruning + `trail:prune` | ✅ |
| R14 | Install helper `trail:install` | ✅ |
| R15 | Publishable config / migrations / styles / assets (tagged) | ✅ |
| R16 | Embedded Blade + Livewire dashboard screens | ⏳ (separate UI workstream) |
| R17 | Opt-in automatic page-view tracking middleware | 🚧 (`TrackPageView` exists; not auto-registered) |
| R18 | Ingest buffer backed by Redis | ⏳ (in-memory buffer shipped) |
| R19 | Pluggable storage drivers (e.g. ClickHouse) + partitioning | ⏳ (v1.0) |

## 4. Installation

```bash
composer require aqu1les/laravel-trail
php artisan vendor:publish --tag="trail-migrations"
php artisan migrate
php artisan vendor:publish --tag="trail-config"   # optional
```

The service provider is auto-discovered and registers the config, routes, facade, publishing tags,
commands, and the ingest flush hook.

## 5. Public API

### Writing

```php
use Trail\Trail\Facades\Trail;

Trail::track('product.viewed');                                   // actor auto-resolved
Trail::track('subscription.created', ['plan' => 'pro'], value: 97.00);
Trail::for($team)->track('member.invited', ['email' => $email]);  // explicit actor
Trail::anonymous()->track('landing.cta_clicked');                 // no actor
Trail::withSession($id)->withContext(['referrer' => 'x'])->track('signup.started');
Trail::sync()->track('payment.captured');                         // force write mode
```

Fluent methods: `for(Model)`, `anonymous()`, `withSession(string)`, `withContext(array)`,
`sync()`/`queue()`/`ingest()`, terminated by `track(string $name, array $properties = [], ?float $value = null)`.
Each facade call starts a fresh `PendingEvent`, so state never leaks between calls.

### Reading

```php
Trail::events()->for($user)->between($start, $end)->get();   // Eloquent collection
Trail::events()->for($user)->paginate(25);                   // LengthAwarePaginator (Livewire)
Trail::count('order.placed')->today();                       // int
Trail::funnel(['signup', 'activated', 'purchase']);          // conversion report
```

`Trail::events()`/`count()` return an `EventQuery` builder (`for`, `anonymous`, `named`, `between`,
`get`, `paginate`, `count`, `today`, `toBuilder`).

### The trait

```php
use Trail\Trail\Concerns\HasTrail;

class User extends Authenticatable
{
    use HasTrail; // ->trailEvents() (MorphMany) and ->track(...)
}
```

### Default actor resolution

`config('trail.subject.resolver')` (default `fn () => auth()->user()`) is used when `track()` is
called without `for()`. Laravel's `morphMap` is respected for clean `subject_type` values.

## 6. Data model

### `trail_events`

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `uuid` | uuid | stable external id (auto-generated) |
| `name` | string, index | `domain.action` convention |
| `subject_type` / `subject_id` | nullableMorphs | the polymorphic actor |
| `session_id` | string, nullable, index | |
| `properties` | json, nullable | arbitrary event payload |
| `context` | json, nullable | url / method / referrer / ip (anonymizable) / user-agent |
| `value` | decimal(20,4), nullable | revenue, duration, score |
| `occurred_at` | timestamp, index | when it happened (≠ created_at) |
| `created_at` | timestamp | when it was recorded |

Composite indexes: `(subject_type, subject_id, occurred_at)`, `(name, occurred_at)`, `(session_id)`.

### `trail_aggregates`

Pre-computed buckets for the dashboard.

| Column | Type | Notes |
|---|---|---|
| `id` | bigIncrements | |
| `period` | string | `hour` / `day` / `week` / `month` |
| `bucket` | timestamp, index | start of the period |
| `name` | string, index | event name |
| `count` | unsignedBigInteger | total occurrences |
| `unique_subjects` | unsignedBigInteger | distinct actors |
| `sum_value` | decimal(20,4), nullable | sum of `value` |
| `created_at` / `updated_at` | timestamp | |

Unique: `(period, bucket, name)`. Recomputed idempotently via `upsert`.

## 7. Recorders (write modes)

Selected by `config('trail.recorder')`, overridable per call. Resolved through `RecorderManager`
(extends `Illuminate\Support\Manager`), so consumers can register custom drivers via `extend()`.

- **`sync`** - writes inside the request. Simplest; adds latency.
- **`queue`** - dispatches `ProcessTrailEvent` onto the configured connection/queue. Recommended default.
- **`ingest`** - `EventBuffer` accumulates rows and flushes in a single bulk `insert` at `flush_at`
  or on app `terminating()`. In-memory today; Redis buffer is ⏳.

## 8. Dashboard, routes & authorization

Routes auto-registered under `config('trail.path')` (default `/trail`) with
`config('trail.middleware')` + the `Authorize` middleware, named `trail.*`.

| Route | Purpose |
|---|---|
| `GET /trail` | Dashboard (Blade + Livewire) |
| `GET /trail/api/events` | Events list (filters: `name`, `subject_type`, `subject_id`, `session_id`, `from`, `to`, `order`, `per_page`) |
| `GET /trail/api/metrics` | `range`, `total_events`, `unique_subjects`, `top_events`, `series` (`period` param) |
| `GET /trail/api/funnel` | `steps[]` → per-step `count`/`rate`/`drop_off` + `overall_conversion` |

**Authorization** - Horizon/Telescope-style. Define the gate from a service provider:

```php
use Trail\Trail\Trail;

Trail::auth(fn ($request) => $request->user()?->isAdmin() ?? false);
```

Default (no callback): allowed only in the `local` environment. A failing gate returns 403.

> **UI stack:** the dashboard is **Blade + Livewire** (server-rendered). Livewire components consume
> the PHP read helpers (`Trail::events()->paginate()`, `Trail::funnel()`) directly server-side. The
> JSON API remains available for external consumers but the dashboard does not depend on it. The
> dashboard screens themselves are built in a dedicated UI workstream.

## 9. Privacy

`config('trail.privacy')`:

- `anonymize_ip` (default `true`) - zero the last IPv4 octet / keep only the IPv6 /48.
- `store_ip` (default `false`) - IP is not stored at all unless enabled.
- `store_user_agent` (default `true`).

`ContextCapture` enriches `context` only for a **routed HTTP request** (never console/queue). Only
event metadata is recorded; message/content bodies are never stored. Whatever lands in `properties`
is supplied by the caller - keep PII out of it.

## 10. Aggregation, retention & commands

| Command | Purpose | Suggested schedule |
|---|---|---|
| `trail:aggregate {--period=} {--since=}` | Recompute `trail_aggregates` (idempotent upsert) | hourly |
| `trail:prune` | Delete events/aggregates beyond `config('trail.retention')` | daily |
| `trail:install` | Publish config + migrations + assets | once |

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('trail:aggregate')->hourly();
Schedule::command('trail:prune')->daily();
```

## 11. Configuration reference (`config/trail.php`)

```php
'connection' => env('TRAIL_DB_CONNECTION'),       // null = default connection
'recorder'   => env('TRAIL_RECORDER', 'queue'),   // sync | queue | ingest
'queue'      => ['connection' => env('TRAIL_QUEUE_CONNECTION'), 'queue' => env('TRAIL_QUEUE', 'default')],
'ingest'     => ['buffer' => env('TRAIL_INGEST_BUFFER', 'redis'), 'flush_at' => 100, 'flush_interval' => 5],
'subject'    => ['resolver' => fn () => auth()->user(), 'model' => 'App\Models\User'],
'path'       => env('TRAIL_PATH', 'trail'),
'middleware' => ['web'],
'auto_track' => ['page_views' => false, 'ignore' => ['trail*', 'horizon*', 'telescope*', 'livewire*', '_debugbar*']],
'privacy'    => ['anonymize_ip' => true, 'store_ip' => false, 'store_user_agent' => true],
'retention'  => ['events_days' => env('TRAIL_RETENTION_DAYS', 90), 'aggregates_days' => 730],
```

Class names and structural defaults are plain literals (not `env()`), overridden by editing the
published config. `env()` is reserved for deploy-varying values.

## 12. Publishing tags

| Tag | Publishes |
|---|---|
| `trail-config` | `config/trail.php` |
| `trail-migrations` | timestamped migrations |
| `trail-styles` | design-system CSS source (`resources/css/trail`) |
| `trail-assets` | compiled assets → `public/vendor/trail` |

## 13. Source layout

```
src/
├── Trail.php                 # facade target: write + read + static auth gate
├── PendingEvent.php          # fluent write builder
├── RecorderManager.php       # driver resolution (Illuminate Manager)
├── Recorders/                # SyncRecorder, QueueRecorder, IngestRecorder
├── Jobs/ProcessTrailEvent.php
├── Models/                   # TrailEvent, TrailAggregate
├── Concerns/HasTrail.php
├── Contracts/Recorder.php
├── Queries/EventQuery.php
├── Support/                  # EventBuffer, Aggregator, ContextCapture, FunnelReport
├── Console/                  # AggregateCommand, PruneCommand, InstallCommand
├── Http/
│   ├── Controllers/{DashboardController, Api/{Events,Metrics,Funnel}Controller}
│   └── Middleware/{Authorize, TrackPageView}
└── Facades/Trail.php
```

## 14. Quality & testing

- Pest (Testbench base, in-memory SQLite). Run: `composer test`.
- Static analysis: PHPStan level 5 via Larastan - zero errors.
- Style: Laravel Pint with `declare(strict_types=1)` enforced on every file.
- CI matrix: PHP 8.3-8.5 × Laravel 11/12/13 × prefer-lowest/stable.
- A Workbench harness (`vendor/bin/testbench serve`) runs the dashboard with ~2k seeded events for
  manual/visual development.

## 15. Roadmap

- **v0.1 (current)** - events + sync/queue/ingest recorders, polymorphic actor + trait,
  auto-registered routes, JSON API, read helpers, aggregation/retention commands, privacy-aware
  context, auth gate.
- **v0.2** - Blade + Livewire dashboard screens (Overview, Events explorer, Funnels, Subject
  timeline); Redis ingest buffer; opt-in page-view tracking wired through config.
- **v1.0** - pluggable storage drivers (optional ClickHouse) + documented partitioning; full CI
  matrix; Packagist release.

## 16. Decisions of record

- Placeholder brand `Trail` / namespace `Trail\Trail` / config+tables+routes prefix `trail` - kept
  consistent and easy to rename.
- Dashboard UI is **Blade + Livewire**, not a compiled Vue SPA (supersedes earlier plans).
- ClickHouse and partitioning are out of v1 (documented evolution).
- `queue` is the recommended recorder; `anonymize_ip` on by default; event content never stored.
