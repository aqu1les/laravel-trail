<?php

declare(strict_types=1);

// config for Trail/Trail

return [

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    | Connection used to store trail data. Null = the app's default connection.
    */
    'connection' => env('TRAIL_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Recorder
    |--------------------------------------------------------------------------
    | How events are written: sync | queue | ingest.
    */
    'recorder' => env('TRAIL_RECORDER', 'queue'),

    'queue' => [
        'connection' => env('TRAIL_QUEUE_CONNECTION'),
        'queue' => env('TRAIL_QUEUE', 'default'),
    ],

    'ingest' => [
        'buffer' => env('TRAIL_INGEST_BUFFER', 'redis'),
        'flush_at' => 100,
        'flush_interval' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracked subject (the actor)
    |--------------------------------------------------------------------------
    | resolver: how Trail discovers the actor when track() is called without for().
    | model: the model considered "default" for screens that list actors.
    */
    'subject' => [
        // How Trail discovers the actor when track() is called without for().
        // null = the authenticated user (auth()->user()).
        //
        // To customize, point this at the class name of an invokable that returns
        // the subject Model (or null). This is the recommended approach: it stays
        // serializable, so `php artisan config:cache` keeps working.
        //
        //     'resolver' => \App\Trail\CurrentTenant::class,
        //
        // where CurrentTenant has an __invoke() method returning the actor.
        // Do not use a closure here: closures break `php artisan config:cache`.
        'resolver' => null,

        // The model considered "default" for screens that list actors.
        // Override this directly in the published config to point at your own model.
        'model' => 'App\Models\User',
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding (sidebar footer)
    |--------------------------------------------------------------------------
    | footer_view: the sidebar footer view. Override it by publishing the view to
    |   resources/views/vendor/trail/partials/, or point this at your own view.
    | back_url / back_label: the "back to your app" link the default footer renders.
    |   The link is hidden when back_url is null.
    */
    'branding' => [
        'footer_view' => 'trail::partials.sidebar-footer',
        'back_url' => env('TRAIL_BACK_URL'),
        'back_label' => 'Voltar ao app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'path' => env('TRAIL_PATH', 'trail'),
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Route registration
    |--------------------------------------------------------------------------
    | Set false to take over route registration yourself by calling
    | Trail::routes() inside your own route group (custom prefix/middleware/domain).
    */
    'register_routes' => env('TRAIL_ROUTES', true),

    /*
    |--------------------------------------------------------------------------
    | JSON / browser API
    |--------------------------------------------------------------------------
    | api.enabled      - master switch for the read + write API routes.
    | browser.enabled  - finer gate for just the browser write endpoint
    |                    (POST /api/ingest). Disable to keep the read API but
    |                    stop browser writes.
    | browser.recorder - recorder the ingest endpoint routes events through.
    |                    null = the global trail.recorder. Recommended: 'ingest'.
    | browser.max_batch      - max events accepted per request.
    | browser.rate_limit     - "attempts,minutes" per user / session / IP.
    | browser.allowed_events - optional event-name allowlist (null = allow any).
    */
    'api' => [
        'enabled' => env('TRAIL_API', true),
    ],

    'browser' => [
        'enabled' => env('TRAIL_BROWSER', true),
        'recorder' => null,
        'max_batch' => 50,
        'rate_limit' => '120,1',
        'allowed_events' => null,
    ],

    /*
    | Compiled dashboard stylesheet (production).
    |
    | Trail's dashboard renders with hand-written component CSS (served at
    | /<path>/trail.css) plus Tailwind utilities. Out of the box those utilities
    | are compiled in-browser via the Tailwind CDN - fine for local use, not for
    | production. For production, build Trail's views into your own Tailwind
    | bundle (publish `trail-styles`, add the package views to your `@source`,
    | `@import "trail/styles.css"`) and point this at the compiled file. When set,
    | the dashboard loads it and skips the CDN. See the Theming docs.
    */
    'stylesheet' => env('TRAIL_DASHBOARD_CSS'),

    /*
    |--------------------------------------------------------------------------
    | Automatic capture
    |--------------------------------------------------------------------------
    */
    'auto_track' => [
        'page_views' => false,
        'event_name' => 'page.viewed',
        'ignore' => ['trail*', 'horizon*', 'telescope*', 'livewire*', '_debugbar*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Context capture
    |--------------------------------------------------------------------------
    | Custom ContextCapture implementation. Must implement ContextCaptureContract.
    | Defaults to the built-in Trail\Trail\Support\ContextCapture.
    */
    'context_capture' => null,

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    */
    'privacy' => [
        'anonymize_ip' => true,
        'store_ip' => false,
        'store_user_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Console context
    |--------------------------------------------------------------------------
    | Attributes captured when events are tracked from Artisan commands or
    | queue workers. capture_command_arguments and capture_server_ip are
    | opt-in as they may expose sensitive values.
    */
    'console' => [
        'capture_hostname' => true,
        'capture_pid' => true,
        'capture_command' => true,
        'capture_command_arguments' => false,
        'capture_server_ip' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'events_days' => env('TRAIL_RETENTION_DAYS', 90),
        'aggregates_days' => 730,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    | Trail auto-registers these on the scheduler. Set to false to manage them
    | yourself in routes/console.php.
    */
    'schedule' => [
        'aggregate' => true,
        'prune' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP servers
    |--------------------------------------------------------------------------
    | Owner-facing, read-only analytics exposed to external MCP clients
    | (Claude Desktop, Cursor, Claude Code). Off by default. Requires
    | `composer require laravel/mcp`. See the Dashboard MCP docs.
    */
    'mcp' => [
        'dashboard' => [
            'enabled' => env('TRAIL_MCP_DASHBOARD', false),
            'path' => env('TRAIL_MCP_DASHBOARD_PATH', 'mcp/trail'),
            'middleware' => [], // stateless; add throttle/etc. as needed
            'token' => env('TRAIL_MCP_DASHBOARD_TOKEN'),
            'expose_properties' => false, // master switch for include_properties
            'events_max' => 200, // hard cap for trail_events.limit
        ],
        // future sibling: 'capture' => [ 'enabled' => false, ... ],
    ],

];
