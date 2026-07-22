<?php

declare(strict_types=1);

namespace Trail\Trail\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A second morph type, distinct from User, so tests can exercise the
 * per-type OR grouping in PathQuery::eventsFor(). Never queried against its
 * own table: PathQuery only ever reads subject_type/subject_id off
 * trail_events, so no schema is registered for it.
 */
class Team extends Model
{
    protected $table = 'teams';

    protected $guarded = [];
}
