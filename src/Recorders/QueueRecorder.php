<?php

declare(strict_types=1);

namespace Trail\Trail\Recorders;

use Trail\Trail\Contracts\Recorder;
use Trail\Trail\Jobs\ProcessTrailEvent;
use Trail\Trail\Models\TrailEvent;

class QueueRecorder implements Recorder
{
    public function record(array $attributes): ?TrailEvent
    {
        ProcessTrailEvent::dispatch($attributes)
            ->onConnection(config('trail.queue.connection'))
            ->onQueue(config('trail.queue.queue'));

        return null;
    }
}
