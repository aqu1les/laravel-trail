<?php

declare(strict_types=1);

namespace Trail\Trail\Recorders;

use Trail\Trail\Contracts\Recorder;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\EventBuffer;

class IngestRecorder implements Recorder
{
    public function __construct(protected EventBuffer $buffer) {}

    public function record(array $attributes): ?TrailEvent
    {
        $this->buffer->push($attributes);

        return null;
    }
}
