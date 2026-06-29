---
name: trail
description: Use when adding or working with user-behavior tracking in this app via the Trail package - recording events with the Trail facade or HasTrail trait, choosing a recorder (sync/queue/ingest), reading events back, the /trail dashboard and its auth gate, or config in config/trail.php.
---

# Trail (user-behavior tracking)

Trail records behavioral events into this app's own database. An event has a name
(`domain.action`), an optional polymorphic actor, free-form `properties`/`context`, and an optional
numeric `value`. This skill is how you use it correctly in this codebase.

## Recording events

```php
use Trail\Trail\Facades\Trail;

Trail::track('product.viewed', ['sku' => $product->sku]);
Trail::track('order.placed', ['order_id' => $order->id], value: $order->total);
```

The fluent builder narrows down before `track()`:

```php
Trail::for($team)->track('member.invited', ['email' => $email]); // attribute to any model
Trail::anonymous()->track('landing.cta_clicked');                // no actor
Trail::withSession($id)->withContext(['ab' => 'b'])->track('signup.started');
Trail::sync()->track('payment.captured');                        // force a recorder for this call
```

`track(string $name, array $properties = [], ?float $value = null)` returns the `TrailEvent` only in
`sync` mode; `queue`/`ingest` return `null`.

When `track()` is called without `for()`, the actor comes from `config('trail.subject.resolver')`
(default `auth()->user()`). Use `Trail::anonymous()` for visitors.

### From a model

Add the trait to whatever you track, then use the shortcut:

```php
use Trail\Trail\Concerns\HasTrail;

class User extends Authenticatable
{
    use HasTrail; // gives ->track(...) and ->trailEvents()
}

$user->track('profile.updated', ['field' => 'avatar']);
```

## Conventions (follow these)

- Event names are lowercase `dot.case`: `order.placed`, `onboarding.step_completed`.
- Put only data you'd want in your DB into `properties`/`context`. Never pass PII or message/content
  bodies - Trail stores metadata, not content.
- `value` is a decimal (revenue, duration, score). In the JSON API it serializes as a string;
  `parseFloat` it on the client.

## Recorders (how events are written)

Set `config('trail.recorder')`; override per call with `sync()`/`queue()`/`ingest()`.

- `sync` - writes in the request. No infra; adds latency. Avoid on hot paths.
- `queue` (default) - dispatches a job. Needs a worker: `php artisan queue:work`.
- `ingest` - buffers and bulk-inserts. Buffer is `redis` (default, across requests) or `memory`.

Add a custom recorder from a service provider:

```php
app(\Trail\Trail\RecorderManager::class)->extend('bigquery', fn ($app) => new BigQueryRecorder());
```

## Reading events

Plain Eloquent, or the fluent read helpers (use these in controllers and Livewire components):

```php
Trail::events()->for($user)->between($start, $end)->paginate(25);
Trail::count('order.placed')->today();          // int
Trail::funnel(['signup', 'activated', 'purchase']); // ['steps' => [...], 'overall_conversion' => 0.25]

$user->trailEvents()->where('name', 'order.placed')->latest('occurred_at')->get();
```

`Trail::events()`/`count()` return an `EventQuery`: `for`, `anonymous`, `named`, `between`, `get`,
`paginate`, `count`, `today`, `toBuilder`.

## Dashboard

Auto-registered under `config('trail.path')` (default `/trail`), with a JSON API at
`/trail/api/{events,metrics,funnel}`. It is gated - by default only the `local` environment can open
it. In production, define who gets in from a service provider:

```php
use Trail\Trail\Trail;

Trail::auth(fn ($request) => $request->user()?->isAdmin() ?? false);
```

## Dashboard MCP

An optional, owner-facing MCP server exposes the read data as curated, read-only tools for an external
MCP client (Claude Code, Claude Desktop, Cursor). It is off by default and needs `composer require
laravel/mcp`. Enable with `TRAIL_MCP_DASHBOARD=true` and a `TRAIL_MCP_DASHBOARD_TOKEN`; it mounts at
`/mcp/trail`. It has its own gate (`Trail::mcpUsing(...)`), which denies everything outside `local` by
default. Connect Claude Code with one command:

```bash
claude mcp add --transport http trail https://your-app.com/mcp/trail \
  --header "Authorization: Bearer your-long-random-token"
```

Tools: `trail_catalog`, `trail_metrics`, `trail_funnel`, `trail_events`, plus the `trail_analysis`
prompt. Event `properties`/`context` stay hidden unless `trail.mcp.dashboard.expose_properties` is on.

## Maintenance commands

- `trail:install` - publish config, migrations, assets.
- `trail:aggregate` - roll events into `trail_aggregates` (schedule hourly).
- `trail:prune` - delete data past the retention window (schedule daily).

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('trail:aggregate')->hourly();
Schedule::command('trail:prune')->daily();
```

## Automatic context capture

Trail fills `context` automatically when `track()` is called - no extra code needed.

**HTTP requests** - captures `url`, `method`, `referrer`, plus IP/user agent per `trail.privacy`.

**Console commands and queue workers** - captures process-level attributes per `trail.console`:

```php
'console' => [
    'capture_hostname'          => true,   // machine hostname
    'capture_pid'               => true,   // process ID
    'capture_command'           => true,   // command name, e.g. "queue:work"
    'capture_command_arguments' => false,  // opt-in - may expose sensitive values
    'capture_server_ip'         => false,  // opt-in
],
```

`withContext()` values are merged on top and win on conflicts.

### Custom context capture

Implement `ContextCaptureContract` and point `trail.context_capture` at your class:

```php
use Trail\Trail\Contracts\ContextCaptureContract;

class MyContextCapture implements ContextCaptureContract
{
    public function fromRequest(\Illuminate\Http\Request $request): array { ... }
    public function fromConsole(): array { ... }
}
```

Or extend the default class and override only the helpers you need:

```php
use Trail\Trail\Support\ContextCapture;

class MyContextCapture extends ContextCapture
{
    protected function resolveHostname(): ?string
    {
        return 'worker-' . gethostname();
    }
}
```

```php
// config/trail.php
'context_capture' => \App\Support\MyContextCapture::class,
```

## Configuration

Everything lives in `config/trail.php` (publish with `php artisan vendor:publish --tag=trail-config`).
Key options: `connection`, `recorder`, `queue`, `ingest.buffer`, `subject.resolver`/`subject.model`,
`path`, `middleware`, `auto_track.page_views`, `privacy.*`, `console.*`, `context_capture`,
`retention.*`. Edit class names and structural defaults directly in the published file - `env()` is
only for deploy-varying values.

## Going to production checklist

1. `php artisan vendor:publish --tag=trail-migrations && php artisan migrate`
2. Add `HasTrail` to your actor model.
3. Run a queue worker (default recorder is `queue`), or set `TRAIL_RECORDER=sync`.
4. Provision Redis if using `ingest`, or set `TRAIL_INGEST_BUFFER=memory`.
5. Define `Trail::auth(...)` so the dashboard is reachable in production.
6. Schedule `trail:aggregate` and `trail:prune`.
