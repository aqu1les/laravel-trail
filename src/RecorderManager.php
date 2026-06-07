<?php

declare(strict_types=1);

namespace Trail\Trail;

use Illuminate\Support\Manager;
use Trail\Trail\Recorders\QueueRecorder;
use Trail\Trail\Recorders\SyncRecorder;

class RecorderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('trail.recorder', 'sync');
    }

    public function createSyncDriver(): SyncRecorder
    {
        return $this->container->make(SyncRecorder::class);
    }

    public function createQueueDriver(): QueueRecorder
    {
        return $this->container->make(QueueRecorder::class);
    }
}
