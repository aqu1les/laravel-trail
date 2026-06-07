<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Redis\Connections\Connection;
use Trail\Trail\Contracts\EventBuffer;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\Concerns\PreparesEventRows;

class RedisEventBuffer implements EventBuffer
{
    use PreparesEventRows;

    public function __construct(
        protected Redis $redis,
        protected int $flushAt = 100,
        protected ?string $connection = null,
        protected string $key = 'trail:events:buffer',
    ) {}

    public function push(array $attributes): void
    {
        $this->connection()->command('rpush', [$this->key, (string) json_encode($attributes)]);

        if ($this->size() >= $this->flushAt) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $connection = $this->connection();

        $raw = $connection->command('lrange', [$this->key, 0, -1]);

        if (! is_array($raw) || $raw === []) {
            return;
        }

        // Simple drain: read then delete. Events pushed between the two calls
        // are picked up by the next flush.
        $connection->command('del', [$this->key]);

        $rows = array_map(
            static fn (mixed $payload): array => (array) json_decode((string) $payload, true),
            array_values($raw),
        );

        TrailEvent::query()->insert($this->prepareRows($rows));
    }

    public function size(): int
    {
        return (int) $this->connection()->command('llen', [$this->key]);
    }

    private function connection(): Connection
    {
        return $this->redis->connection($this->connection);
    }
}
