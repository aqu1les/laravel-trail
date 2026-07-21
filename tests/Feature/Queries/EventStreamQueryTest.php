<?php

declare(strict_types=1);

use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Queries\EventStreamQuery;

it('compiles the search to a case-insensitive predicate on every driver', function () {
    // The suite runs on SQLite, whose LIKE is already case-insensitive, so the
    // Postgres regression (plain LIKE is case-SENSITIVE there) would slip
    // through unnoticed. Compile the same predicate under each grammar instead.
    config()->set('database.connections.probe_pgsql', ['driver' => 'pgsql', 'database' => 'probe']);
    config()->set('database.connections.probe_mysql', ['driver' => 'mysql', 'database' => 'probe']);

    $compile = function (string $connection): string {
        $query = TrailEvent::on($connection)->newQuery();

        $applySearch = new ReflectionMethod(EventStreamQuery::class, 'applySearch');
        $applySearch->invoke(EventStreamQuery::inWindow(now()->subDays(7)), $query, 'Doralice');

        return $query->toSql();
    };

    // Postgres: ilike, plus the ::text cast that makes json and bigint columns matchable.
    expect($compile('probe_pgsql'))
        ->toContain('ilike')
        ->toContain('"properties"::text')
        ->toContain('"subject_id"::text')
        ->not->toContain(' like ');

    // MySQL: plain like (its default collation is already case-insensitive).
    expect($compile('probe_mysql'))->toContain('like');
});
