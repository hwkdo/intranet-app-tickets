<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Enums;

enum ZammadWebhookOutcomeStatus: string
{
    case Processed = 'processed';
    case SkippedInternal = 'skipped_internal';
    case SkippedNotAgent = 'skipped_not_agent';
    case SkippedNoEmail = 'skipped_no_email';
    case SkippedNoUser = 'skipped_no_user';
    case SkippedNoTicket = 'skipped_no_ticket';
    case SkippedStatus = 'skipped_status';
    case SkippedUnsupported = 'skipped_unsupported';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processed => 'Verarbeitet',
            self::SkippedInternal => 'Übersprungen (intern)',
            self::SkippedNotAgent => 'Übersprungen (kein Agent)',
            self::SkippedNoEmail => 'Übersprungen (keine E-Mail)',
            self::SkippedNoUser => 'Übersprungen (kein Intranet-User)',
            self::SkippedNoTicket => 'Übersprungen (keine Ticket-ID)',
            self::SkippedStatus => 'Übersprungen (Status)',
            self::SkippedUnsupported => 'Übersprungen (nicht unterstützt)',
            self::Failed => 'Fehlgeschlagen',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Processed => 'green',
            self::Failed => 'red',
            default => 'zinc',
        };
    }
}
