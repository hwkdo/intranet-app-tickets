<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppTickets\Ai\TeamsTicketAiPrompt;
use Hwkdo\IntranetAppTickets\Data\GeneratedTeamsTicketContent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TeamsTicketContentGenerator
{
    public function __construct(
        private readonly TicketsAppSettingsStore $settingsStore,
        private readonly IntranetAiGatewayInterface $gateway,
    ) {}

    public function generate(
        string $rawContent,
        ?string $displayName,
        string $sourceLabel,
        string $fallbackSubject,
        string $fallbackBody,
    ): GeneratedTeamsTicketContent {
        $settings = $this->settingsStore->current();

        if (! $settings->teamsTicketAiEnabled) {
            return new GeneratedTeamsTicketContent(
                subject: $fallbackSubject,
                body: $fallbackBody,
                generatedByAi: false,
            );
        }

        try {
            return $this->generateWithAi(
                rawContent: $rawContent,
                displayName: $displayName,
                sourceLabel: $sourceLabel,
            );
        } catch (Throwable $exception) {
            Log::warning('Teams-Bot KI-Ticketformulierung fehlgeschlagen, Fallback auf Rohtext', [
                'message' => $exception->getMessage(),
            ]);

            return new GeneratedTeamsTicketContent(
                subject: $fallbackSubject !== '' ? $fallbackSubject : $this->buildFallbackSubject($fallbackBody),
                body: $fallbackBody,
                generatedByAi: false,
            );
        }
    }

    private function generateWithAi(
        string $rawContent,
        ?string $displayName,
        string $sourceLabel,
    ): GeneratedTeamsTicketContent {
        $messages = [
            ['role' => 'system', 'content' => TeamsTicketAiPrompt::SYSTEM],
            ['role' => 'user', 'content' => $this->buildPrompt($rawContent, $displayName, $sourceLabel)],
        ];

        $result = $this->gateway->chat(
            $messages,
            new AiRequestContext(
                appIdentifier: 'tickets',
                capability: AiCapability::Text,
            ),
            [
                'response_format' => ['type' => 'json_object'],
            ],
        );

        $parsed = TeamsTicketAiPrompt::parseResponse($result->content);

        return new GeneratedTeamsTicketContent(
            subject: $parsed['betreff'],
            body: $parsed['inhalt'],
            generatedByAi: true,
        );
    }

    private function buildPrompt(string $rawContent, ?string $displayName, string $sourceLabel): string
    {
        $sender = filled($displayName) ? $displayName : 'Unbekannt';

        return <<<PROMPT
Formuliere aus der folgenden Teams-Nachricht einen IT-Support-Ticket-Betreff und -Inhalt.

Nutzer: {$sender}
Quelle: {$sourceLabel}
Kategorie: IT-Support

--- ROH-NACHRICHT ---
{$rawContent}
--- ENDE ---
PROMPT;
    }

    private function buildFallbackSubject(string $body): string
    {
        $firstLine = trim((string) Str::of($body)->before("\n"));

        return Str::limit($firstLine !== '' ? $firstLine : $body, 150, '');
    }
}
