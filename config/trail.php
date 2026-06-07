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
        'resolver' => fn () => auth()->user(),

        // The model considered "default" for screens that list actors.
        // Override this directly in the published config to point at your own model.
        'model' => 'App\Models\User',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'path' => env('TRAIL_PATH', 'trail'),
    'middleware' => ['web'],

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

];
