<?php

declare(strict_types=1);

namespace Trail\Trail\Recorders;

use Trail\Trail\Contracts\Recorder;
use Trail\Trail\Models\TrailEvent;

class SyncRecorder implements Recorder
{
    public function record(array $attributes): ?TrailEvent
    {
        return TrailEvent::create($attributes);
    }
}
