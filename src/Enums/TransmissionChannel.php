<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Enums;

enum TransmissionChannel: string
{
    case Zammad = 'zammad';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Zammad => 'Zammad API',
            self::Email => 'E-Mail',
        };
    }
}
