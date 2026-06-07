<?php

declare(strict_types=1);

namespace Trail\Trail;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Trail\Trail\Models\TrailEvent;

class Trail
{
    /**
     * The callback that authorizes access to the dashboard.
     *
     * @var (Closure(Request): bool)|null
     */
    protected static ?Closure $authUsing = null;

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
}
