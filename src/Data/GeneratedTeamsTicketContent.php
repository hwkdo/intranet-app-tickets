<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

class GeneratedTeamsTicketContent
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
        public readonly bool $generatedByAi = true,
    ) {}
}
