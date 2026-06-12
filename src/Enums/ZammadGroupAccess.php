<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Enums;

enum ZammadGroupAccess: string
{
    case Read = 'read';
    case Create = 'create';
    case Change = 'change';
    case Overview = 'overview';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Lesen',
            self::Create => 'Erstellen',
            self::Change => 'Bearbeiten',
            self::Overview => 'Übersicht',
            self::Full => 'Vollzugriff',
        };
    }

    /**
     * @return list<self>
     */
    public static function optionsForIntranet(): array
    {
        return [
            self::Create,
            self::Read,
            self::Change,
            self::Full,
        ];
    }
}
