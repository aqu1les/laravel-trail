<?php

declare(strict_types=1);

namespace Trail\Trail\Queries;

use Illuminate\Database\Eloquent\Builder;

/**
 * The "subject_type|subject_id" token the dashboard puts in URLs and menus.
 *
 * @internal Not covered by the package's backwards-compatibility promise.
 */
final class SubjectKey
{
    private function __construct(
        public readonly string $type,
        public readonly string $id,
    ) {}

    /** Parse a token, or null when it is empty or malformed. */
    public static function parse(?string $token): ?self
    {
        if ($token === null || ! str_contains($token, '|')) {
            return null;
        }

        [$type, $id] = explode('|', $token, 2);

        return $type === '' || $id === '' ? null : new self($type, $id);
    }

    /** Build a key from a pair of column values, or null when either is missing. */
    public static function of(?string $type, mixed $id): ?self
    {
        return $type === null || $type === '' || $id === null || $id === ''
            ? null
            : new self($type, (string) $id);
    }

    public function __toString(): string
    {
        return $this->type.'|'.$this->id;
    }

    /** The short class name, for display. */
    public function label(): string
    {
        return class_basename($this->type);
    }

    /**
     * Narrow a query to this subject. Hits the
     * (subject_type, subject_id, occurred_at) composite index.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyTo(Builder $query): Builder
    {
        return $query->where('subject_type', $this->type)->where('subject_id', $this->id);
    }
}
