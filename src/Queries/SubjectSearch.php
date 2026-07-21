<?php

declare(strict_types=1);

namespace Trail\Trail\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Trail\Trail\Models\TrailEvent;

/**
 * Finds subjects by their display identity.
 *
 * An actor's name lives on the morphed model, not on the events table, so
 * matching one means asking each model its own (indexed) table.
 *
 * @internal Not covered by the package's backwards-compatibility promise.
 */
final class SubjectSearch
{
    /** The identity columns worth searching, when the table has them. */
    private const COLUMNS = ['name', 'email'];

    /**
     * The distinct subject types present in an event query.
     *
     * @param  Builder<TrailEvent>  $events
     * @return list<string>
     */
    public static function distinctTypes(Builder $events): array
    {
        // Clone: reorder() and the added wheres would otherwise leak back into
        // the caller's builder, which has no reason to expect them.
        return (clone $events)->reorder()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Subject ids whose name or email matches the term, keyed by subject type.
     *
     * Types whose model cannot be resolved, or whose table has no identity
     * column, are skipped: they are only reachable by id. Capped per type so a
     * broad term cannot load an entire table.
     *
     * @param  list<string>  $types
     * @return array<string, list<mixed>>
     */
    public static function matchingIds(string $term, array $types, int $cap): array
    {
        $matched = [];

        foreach ($types as $type) {
            $class = Relation::getMorphedModel($type) ?? $type;
            if (! class_exists($class)) {
                continue;
            }

            $model = new $class;
            $schema = Schema::connection($model->getConnectionName());
            $columns = array_values(array_filter(
                self::COLUMNS,
                fn (string $column): bool => $schema->hasColumn($model->getTable(), $column)
            ));

            if ($columns === []) {
                continue;
            }

            $ids = $class::query()
                ->where(function ($query) use ($columns, $term): void {
                    foreach ($columns as $column) {
                        // whereLike stays case-insensitive on every driver.
                        $query->orWhereLike($column, '%'.$term.'%');
                    }
                })
                ->limit($cap)
                ->pluck($model->getKeyName())
                ->all();

            if ($ids !== []) {
                $matched[$type] = $ids;
            }
        }

        return $matched;
    }
}
