<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppTickets\Enums\TicketListItemType;

readonly class TicketListItem
{
    public function __construct(
        public TicketListItemType $type,
        public int $id,
        public ?string $number,
        public string $title,
        public string $statusLabel,
        public ?CarbonInterface $updatedAt,
        public string $url,
        public bool $isUnread = false,
        public ?string $badge = null,
    ) {}
}
