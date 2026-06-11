<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Enums;

enum TicketFormType: string
{
    case ItSupport = 'it_support';
    case Webchange = 'webchange';
    case Marketing = 'marketing';
    case Hausmeisterservice = 'hausmeisterservice';
    case Druckauftrag = 'druckauftrag';
    case Vertragsmanagement = 'vertragsmanagement';
    case Zollauktion = 'zollauktion';
    case Moodle = 'moodle';

    public function label(): string
    {
        return match ($this) {
            self::ItSupport => 'IT-Support',
            self::Webchange => 'Webchange',
            self::Marketing => 'Marketing',
            self::Hausmeisterservice => 'Hausmeisterservice',
            self::Druckauftrag => 'Druckauftrag',
            self::Vertragsmanagement => 'Vertragsmanagement',
            self::Zollauktion => 'Zollauktion / Anlagenverkauf',
            self::Moodle => 'Moodle',
        };
    }
}
