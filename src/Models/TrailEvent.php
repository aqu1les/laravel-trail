<?php

declare(strict_types=1);

namespace Trail\Trail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $uuid
 * @property string $name
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property string|null $session_id
 * @property array<string, mixed>|null $properties
 * @property array<string, mixed>|null $context
 * @property float|null $value
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 */
class TrailEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
        'context' => 'array',
        'value' => 'decimal:4',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }

            if (empty($event->occurred_at)) {
                $event->occurred_at = $event->freshTimestamp();
            }
        });
    }

    public function getConnectionName(): ?string
    {
        return config('trail.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return 'trail_events';
    }

    /**
     * The polymorphic actor the event is attributed to.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
