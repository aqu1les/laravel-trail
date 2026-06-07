<?php

declare(strict_types=1);

namespace Trail\Trail\Contracts;

interface EventBuffer
{
    /**
     * Add an event to the buffer (may trigger a flush when the threshold is hit).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function push(array $attributes): void;

    /**
     * Persist all buffered events in a single batch and clear the buffer.
     */
    public function flush(): void;

    /**
     * Number of events currently buffered.
     */
    public function size(): int;
}
