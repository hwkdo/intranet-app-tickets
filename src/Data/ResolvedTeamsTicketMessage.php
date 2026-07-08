<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

class ResolvedTeamsTicketMessage
{
    public function __construct(
        public readonly string $content,
        public readonly bool $contentFromQuote,
    ) {}
}
