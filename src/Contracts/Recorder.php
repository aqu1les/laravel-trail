<?php

declare(strict_types=1);

namespace Trail\Trail\Contracts;

use Trail\Trail\Models\TrailEvent;

interface Recorder
{
    /**
     * Record a single event.
     *
     * @param  array<string, mixed>  $attributes
     * @return TrailEvent|null The persisted event when written synchronously, null otherwise.
     */
    public function record(array $attributes): ?TrailEvent;
}
