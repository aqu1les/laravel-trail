<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Trail\Trail\Contracts\EventBuffer;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\Concerns\PreparesEventRows;

class MemoryEventBuffer implements EventBuffer
{
    use PreparesEventRows;

    /** @var list<array<string, mixed>> */
    protected array $rows = [];

    public function __construct(protected int $flushAt = 100) {}

    public function push(array $attributes): void
    {
        $this->rows[] = $attributes;

        if (count($this->rows) >= $this->flushAt) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->rows === []) {
            return;
        }

        $rows = $this->rows;
        $this->rows = [];

        TrailEvent::query()->insert($this->prepareRows($rows));
    }

    public function size(): int
    {
        return count($this->rows);
    }
}
