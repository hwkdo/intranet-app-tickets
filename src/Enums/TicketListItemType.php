<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Enums;

enum TicketListItemType: string
{
    case Zammad = 'zammad';
    case Request = 'request';
}
