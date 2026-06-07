<?php

declare(strict_types=1);

namespace Trail\Trail\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Trail\Trail\PendingEvent for(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \Trail\Trail\PendingEvent anonymous()
 * @method static \Trail\Trail\PendingEvent withSession(string $sessionId)
 * @method static \Trail\Trail\PendingEvent withContext(array<string, mixed> $context)
 * @method static \Trail\Trail\PendingEvent sync()
 * @method static \Trail\Trail\PendingEvent queue()
 * @method static \Trail\Trail\PendingEvent ingest()
 * @method static \Trail\Trail\Models\TrailEvent|null track(string $name, array<string, mixed> $properties = [], ?float $value = null)
 *
 * @see \Trail\Trail\Trail
 */
class Trail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Trail\Trail\Trail::class;
    }
}
