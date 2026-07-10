<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

use Hwkdo\IntranetAppBase\Contracts\HasAiSettings;
use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Hwkdo\IntranetAppTickets\Enums\TeamsTicketAiProvider;

class AppSettings extends BaseAppSettings implements HasAiSettings
{
    public function __construct(
        #[Description('Maximale Anzahl von Tickets pro API-Abfrage')]
        public int $maxTicketsPerPage = 100,

        #[Description('Zammad-Rolle für Intranet-Benutzer (On-Behalf-of Ticket-Erstellung)')]
        public ?int $zammadIntranetUserRoleId = null,

        #[Description('KI zur Formulierung von Betreff und Inhalt bei Teams-Bot-Tickets (IT-Support)')]
        public bool $teamsTicketAiEnabled = true,

        #[Description('KI-Backend für Teams-Bot-Tickets (Laravel-AI-Provider-Name)')]
        public TeamsTicketAiProvider $teamsTicketAiProvider = TeamsTicketAiProvider::Langdock,

        #[Description('Modell für Teams-Bot-Tickets bei Open Web UI / Ollama (z. B. gpt-oss:20b). Leer = Fallback aus config/intranet-app-tickets.php.')]
        public string $teamsTicketAiModelOpenWebUi = 'gpt-oss:20b',

        #[Description('Modell für Teams-Bot-Tickets bei Langdock. Leer = Fallback aus config/intranet-app-tickets.php.')]
        public string $teamsTicketAiModelLangdock = 'gpt-4o',

        #[Description('KI-Text-Provider überschreiben (leer = Intranet-Base-Default)')]
        public ?AiProvider $aiTextProviderOverride = null,

        #[Description('KI-Text-Modell überschreiben (leer = Base- bzw. Provider-Default)')]
        public ?string $aiTextModelOverride = null,

        #[Description('KI-Bild-Provider überschreiben (leer = Intranet-Base-Default)')]
        public ?AiProvider $aiImageProviderOverride = null,

        #[Description('KI-Bild-Modell überschreiben (leer = Base- bzw. Provider-Default)')]
        public ?string $aiImageModelOverride = null,

        #[Description('OpenWebUI-Modell für den Tickets-KI-Chat (MCP-Server)')]
        public string $openWebUiModel = 'intranet-app-tickets',
    ) {}

    public function textProviderOverride(): ?AiProvider
    {
        if ($this->aiTextProviderOverride !== null) {
            return $this->aiTextProviderOverride;
        }

        return match ($this->teamsTicketAiProvider) {
            TeamsTicketAiProvider::OpenWebUi => AiProvider::OpenWebUi,
            TeamsTicketAiProvider::Langdock => AiProvider::Langdock,
        };
    }

    public function textModelOverride(): ?string
    {
        if (is_string($this->aiTextModelOverride) && trim($this->aiTextModelOverride) !== '') {
            return trim($this->aiTextModelOverride);
        }

        $legacy = match ($this->teamsTicketAiProvider) {
            TeamsTicketAiProvider::OpenWebUi => $this->teamsTicketAiModelOpenWebUi,
            TeamsTicketAiProvider::Langdock => $this->teamsTicketAiModelLangdock,
        };

        return trim($legacy) !== '' ? trim($legacy) : null;
    }

    public function imageProviderOverride(): ?AiProvider
    {
        return $this->aiImageProviderOverride;
    }

    public function imageModelOverride(): ?string
    {
        if (! is_string($this->aiImageModelOverride)) {
            return null;
        }

        $trimmed = trim($this->aiImageModelOverride);

        return $trimmed === '' ? null : $trimmed;
    }
}
