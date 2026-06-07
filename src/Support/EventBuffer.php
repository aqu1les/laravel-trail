<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Illuminate\Support\Str;
use Trail\Trail\Models\TrailEvent;

class EventBuffer
{
    /** @var list<array<string, mixed>> */
    protected array $rows = [];

    public function __construct(protected int $flushAt = 100) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
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

        $now = now();

        $prepared = array_map(function (array $row) use ($now): array {
            $row['uuid'] ??= (string) Str::uuid();
            $row['properties'] = isset($row['properties']) ? json_encode($row['properties']) : null;
            $row['context'] = isset($row['context']) ? json_encode($row['context']) : null;
            $row['created_at'] ??= $now;
            $row['occurred_at'] ??= $now;

            return $row;
        }, $rows);

        TrailEvent::query()->insert($prepared);
    }

    public function size(): int
    {
        return count($this->rows);
    }
}
