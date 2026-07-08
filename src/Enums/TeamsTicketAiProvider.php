<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Enums;

enum TeamsTicketAiProvider: string
{
    case Langdock = 'langdock';
    case OpenWebUi = 'openwebui';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Langdock->value => 'Langdock',
            self::OpenWebUi->value => 'Open Web UI (Ollama)',
        ];
    }
}
