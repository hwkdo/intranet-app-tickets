<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Enums;

enum TicketRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Dispatched = 'dispatched';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Zur Genehmigung',
            self::Approved => 'Genehmigt',
            self::Rejected => 'Abgelehnt',
            self::Dispatched => 'Übermittelt',
            self::Failed => 'Fehlgeschlagen',
        };
    }
}
