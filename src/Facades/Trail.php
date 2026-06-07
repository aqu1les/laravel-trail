<?php

namespace Trail\Trail\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Trail\Trail\Trail
 */
class Trail extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Trail\Trail\Trail::class;
    }
}
