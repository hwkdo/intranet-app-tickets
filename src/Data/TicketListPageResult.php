<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

readonly class TicketListPageResult
{
    /**
     * @param  list<TicketListItem>  $newItems
     */
    public function __construct(
        public array $newItems,
        public bool $hasMore,
    ) {}
}
