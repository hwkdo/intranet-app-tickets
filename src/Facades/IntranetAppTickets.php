<?php

namespace Hwkdo\IntranetAppTickets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppTickets\IntranetAppTickets
 */
class IntranetAppTickets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppTickets\IntranetAppTickets::class;
    }
}
