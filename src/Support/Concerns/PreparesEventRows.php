<?php

declare(strict_types=1);

namespace Trail\Trail\Support\Concerns;

use Illuminate\Support\Str;

trait PreparesEventRows
{
    /**
     * Normalize buffered rows for a raw bulk insert (which bypasses model casts/events):
     * ensure a uuid, JSON-encode json columns, and fill timestamps.
     *
     * @param  list<array<array-key, mixed>>  $rows
     * @return list<array<array-key, mixed>>
     */
    protected function prepareRows(array $rows): array
    {
        $now = now();

        return array_map(function (array $row) use ($now): array {
            $row['uuid'] ??= (string) Str::uuid();
            $row['properties'] = $this->encodeJsonColumn($row['properties'] ?? null);
            $row['context'] = $this->encodeJsonColumn($row['context'] ?? null);
            $row['created_at'] ??= $now;
            $row['occurred_at'] ??= $now;

            return $row;
        }, $rows);
    }

    private function encodeJsonColumn(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : (string) json_encode($value);
    }
}
