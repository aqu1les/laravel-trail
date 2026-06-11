<?php

declare(strict_types=1);

namespace Trail\Trail\Tests\Fixtures;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Trail\Trail\Concerns\HasTrail;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasTrail;

    protected $table = 'users';

    protected $guarded = [];
}
