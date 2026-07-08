<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Listeners;

use Hwkdo\IntranetAppTickets\Jobs\CreateTicketFromTeamsMessageJob;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketCommandParser;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketMessageContentResolver;
use Hwkdo\MsGraphLaravel\Events\TeamsBotMessageReceived;

class HandleTeamsBotTicketCommand
{
    public function __construct(
        private readonly TeamsTicketCommandParser $parser,
        private readonly TeamsTicketMessageContentResolver $contentResolver,
    ) {}

    /**
     * Verarbeitet eingehende Teams-Nachrichten. Gibt einen Bestätigungstext zurück, wenn ein
     * Ticket-Befehl erkannt wurde (der Handler sendet diesen als sofortige Antwort). Andernfalls
     * null, damit andere Listener bzw. die Standard-Antwort greifen.
     */
    public function handle(TeamsBotMessageReceived $event): ?string
    {
        $message = $event->message;

        $parsed = $this->parser->parse($message->text);

        if ($parsed === null) {
            return null;
        }

        $resolvedMessage = $this->contentResolver->resolve(
            parsedBody: $parsed->body,
            quotedText: $message->quotedText,
            quotedSenderName: $message->quotedSenderName,
            isDirectMessage: $message->isDirectMessage(),
        );

        if (trim($resolvedMessage->content) === '') {
            return 'Ich kann ein Ticket für dich erstellen – bitte beschreibe kurz dein Anliegen, '
                .'z. B. „erstelle mir ein Ticket, dass der Drucker im 2. OG defekt ist", '
                .'oder zitiere die betreffende Nachricht.';
        }

        CreateTicketFromTeamsMessageJob::dispatch(
            upn: $message->upn,
            azureUserId: $message->azureUserId,
            displayName: $message->displayName,
            rawContent: $resolvedMessage->content,
            fallbackSubject: $this->buildFallbackSubject($resolvedMessage->content),
            fallbackBody: $resolvedMessage->content,
            sourceLabel: $message->sourceLabel(),
            contentFromQuote: $resolvedMessage->contentFromQuote,
            quotedSenderAzureId: $message->quotedSenderAzureId,
            quotedSenderName: $message->quotedSenderName,
            quotedMessageId: $message->quotedMessageId,
            quotedText: $message->quotedText,
            activity: $message->activity,
            conversationRef: $message->conversationRef,
        );

        return 'Alles klar, ich erstelle dein Ticket und melde mich gleich mit der Ticketnummer.';
    }

    private function buildFallbackSubject(string $content): string
    {
        $firstLine = trim((string) str($content)->before("\n"));

        return str($firstLine !== '' ? $firstLine : $content)->limit(120, '')->toString();
    }
}
