<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Enums;

enum TicketFilterEnum: string
{
    case All = 'all';
    case Open = 'open';
    case Closed = 'closed';
}
