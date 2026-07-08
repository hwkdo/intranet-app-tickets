<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

class ParsedTeamsTicketCommand
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}

    public function hasContent(): bool
    {
        return trim($this->body) !== '';
    }
}
