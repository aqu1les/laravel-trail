<?php

declare(strict_types=1);

namespace Trail\Trail\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Trail\Trail\Facades\Trail;
use Trail\Trail\Models\TrailEvent;

/**
 * Adds trail tracking helpers to a subject model.
 */
trait HasTrail
{
    /**
     * The events attributed to this subject.
     *
     * @return MorphMany<TrailEvent, $this>
     */
    public function trailEvents(): MorphMany
    {
        return $this->morphMany(TrailEvent::class, 'subject');
    }

    /**
     * Record an event attributed to this subject.
     *
     * @param  array<string, mixed>  $properties
     */
    public function track(string $name, array $properties = [], ?float $value = null): ?TrailEvent
    {
        return Trail::for($this)->track($name, $properties, $value);
    }
}
