<?php

declare(strict_types=1);

namespace Trail\Trail\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Trail\Trail\Concerns\HasTrail;

class User extends Model
{
    use HasTrail;

    protected $table = 'users';

    protected $guarded = [];
}
