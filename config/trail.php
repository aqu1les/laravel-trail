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
