<?php

declare(strict_types=1);

namespace Trail\Trail;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Queries\EventQuery;
use Trail\Trail\Support\FunnelReport;

class Trail
{
    /**
     * The callback that authorizes access to the dashboard.
     *
     * @var (Closure(Request): bool)|null
     */
    protected static ?Closure $authUsing = null;

    /**
     * The callback that authorizes browser event ingestion.
     *
     * @var (Closure(Request): bool)|null
     */
    protected static ?Closure $ingestUsing = null;

    public function __construct(protected RecorderManager $recorders) {}

    /**
     * Register the callback used to authorize dashboard access.
     *
     * @param  (Closure(Request): bool)|null  $callback
     */
    public static function auth(?Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * Determine if the given request may access the dashboard.
     *
     * Defaults to local-environment-only when no callback is registered.
     */
    public static function check(Request $request): bool
    {
        $callback = static::$authUsing;

        return $callback !== null
            ? (bool) $callback($request)
            : app()->environment('local');
    }

    /**
     * Register the callback used to authorize browser event ingestion.
     *
     * @param  (Closure(Request): bool)|null  $callback
     */
    public static function ingestUsing(?Closure $callback): void
    {
        static::$ingestUsing = $callback;
    }

    /**
     * Determine if the given request may emit browser events.
     *
     * Defaults to allow (including anonymous), since that is what makes
     * Amplitude-style capture work out of the box. Write protection is CSRF +
     * same-origin + rate limit + server-side subject resolution.
     */
    public static function canIngest(Request $request): bool
    {
        $callback = static::$ingestUsing;

        return $callback !== null ? (bool) $callback($request) : true;
    }

    public function newPendingEvent(): PendingEvent
    {
        return new PendingEvent($this->recorders);
    }

    public function for(Model $subject): PendingEvent
    {
        return $this->newPendingEvent()->for($subject);
    }

    public function anonymous(): PendingEvent
    {
        return $this->newPendingEvent()->anonymous();
    }

    public function withSession(string $sessionId): PendingEvent
    {
        return $this->newPendingEvent()->withSession($sessionId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): PendingEvent
    {
        return $this->newPendingEvent()->withContext($context);
    }

    public function sync(): PendingEvent
    {
        return $this->newPendingEvent()->sync();
    }

    public function queue(): PendingEvent
    {
        return $this->newPendingEvent()->queue();
    }

    public function ingest(): PendingEvent
    {
        return $this->newPendingEvent()->ingest();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function track(string $name, array $properties = [], ?float $value = null): ?TrailEvent
    {
        return $this->newPendingEvent()->track($name, $properties, $value);
    }

    /**
     * Start a fluent read query over recorded events.
     */
    public function events(): EventQuery
    {
        return new EventQuery;
    }

    /**
     * Start a read query scoped to a single event name.
     */
    public function count(string $name): EventQuery
    {
        return $this->events()->named($name);
    }

    /**
     * The event name used for automatic page-view tracking.
     *
     * Single source of truth for the middleware that records page views and the
     * dashboard screens that hide them by default.
     */
    public function pageViewName(): string
    {
        return (string) config('trail.auto_track.event_name', 'page.viewed');
    }

    /**
     * Return the compiled dashboard CSS as an inline <style> tag.
     *
     * Mirrors the Laravel Pulse pattern: dist/trail.css is pre-compiled
     * and shipped with the package, so consumers need zero build config.
     */
    public static function styles(): string
    {
        $path = __DIR__.'/../dist/trail.css';

        if (($css = @file_get_contents($path)) === false) {
            throw new \RuntimeException("Trail: unable to load compiled CSS [{$path}].");
        }

        return '<style>'.$css.'</style>';
    }

    /**
     * Build a funnel conversion report for an ordered list of event names.
     *
     * @param  array<int, string>  $steps
     * @return array<string, mixed>
     */
    public function funnel(array $steps): array
    {
        return app(FunnelReport::class)->build(array_values($steps));
    }
}
