<?php

namespace Ghanem\Bee\Facades;

use Illuminate\Support\Facades\Facade;

class Bee extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'ghanem-bee';
    }
}