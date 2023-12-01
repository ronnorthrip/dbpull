<?php

namespace RonNorthrip\DBPull\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RonNorthrip\DBPull\DBPull
 */
class DBPull extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \RonNorthrip\DBPull\DBPull::class;
    }
}
