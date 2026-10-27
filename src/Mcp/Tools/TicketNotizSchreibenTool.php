<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Mcp\Tools;

use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use RuntimeException;
use Throwable;

#[IsOpenWorld]
#[IsDestructive(false)]
class TicketNotizSchreibenTool extends Tool
{
    protected string $name = 'ticket_notiz_schreiben';

    protected string $description = 'Schreibt eine öffentliche Notiz (Kundenantwort) in ein bestehendes Zammad-Ticket. Nutzen, wenn der Nutzer dem Bearbeiter antworten oder eine Nachricht ins Ticket schreiben möchte. Nur möglich, wenn der angemeldete Nutzer der Kunde des Tickets ist. Die Notiz ist öffentlich und für den Support sichtbar, keine interne Notiz. Pflicht: inhalt sowie ticket_id (interne ID aus tickets_anzeigen oder ticket_detail_anzeigen) oder ticket_number (sichtbare Ticketnummer, z. B. 3152005). Nicht für Genehmigungsanfragen.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user instanceof Authenticatable) {
            return Response::error('Authentifizierung erforderlich.');
        }

        $validated = $request->validate([
            'ticket_id' => ['nullable', 'integer', 'min:1'],
            'ticket_number' => ['nullable', 'string', 'max:32'],
            'number' => ['nullable', 'string', 'max:32'],
            'inhalt' => ['nullable', 'string', 'max:10000'],
            'body' => ['nullable', 'string', 'max:10000'],
            'nachricht' => ['nullable', 'string', 'max:10000'],
            'text' => ['nullable', 'string', 'max:10000'],
        ]);

        $inhalt = trim((string) ($validated['inhalt'] ?? $validated['body'] ?? $validated['nachricht'] ?? $validated['text'] ?? ''));

        if ($inhalt === '') {
            return Response::error('Das Feld inhalt (oder body/nachricht) ist erforderlich.');
        }

        $ticketId = isset($validated['ticket_id']) ? (int) $validated['ticket_id'] : null;
        $ticketNumber = trim((string) ($validated['ticket_number'] ?? $validated['number'] ?? ''));

        if ($ticketId === null && $ticketNumber === '') {
            return Response::error('ticket_id oder ticket_number ist erforderlich. ticket_id ist die interne ID aus tickets_anzeigen oder ticket_detail_anzeigen, ticket_number die sichtbare Nummer (z. B. 3152005).');
        }

        Log::info('ticket_notiz_schreiben called', [
            'user_id' => $user->getAuthIdentifier(),
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber !== '' ? $ticketNumber : null,
        ]);

        $ticketService = app(ZammadTicketService::class);

        try {
            $ticketId = $this->resolveAndReply($ticketService, $user, $ticketId, $ticketNumber, $inhalt);
        } catch (RuntimeException $exception) {
            Log::warning('ticket_notiz_schreiben rejected', [
                'user_id' => $user->getAuthIdentifier(),
                'ticket_id' => $ticketId,
                'ticket_number' => $ticketNumber !== '' ? $ticketNumber : null,
                'message' => $exception->getMessage(),
            ]);

            return Response::error($this->publicErrorMessage($exception));
        } catch (Throwable $exception) {
            Log::error('ticket_notiz_schreiben failed', [
                'user_id' => $user->getAuthIdentifier(),
                'ticket_id' => $ticketId,
                'message' => $exception->getMessage(),
            ]);

            return Response::error('Die öffentliche Notiz konnte nicht gespeichert werden.');
        }

        $ticket = $ticketService->getTicketForUser($user, $ticketId);

        return Response::structured($this->formatResult($ticketId, is_array($ticket) ? $ticket : null, $inhalt));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket_id' => $schema->integer()
                ->description('Interne Zammad-Ticket-ID aus tickets_anzeigen oder ticket_detail_anzeigen (Feld id, nicht die sichtbare Nummer).')
                ->nullable(),
            'ticket_number' => $schema->string()
                ->description('Sichtbare Ticketnummer, z. B. 3152005. Alternative zu ticket_id.')
                ->nullable(),
            'number' => $schema->string()
                ->description('Alias für ticket_number.')
                ->nullable(),
            'inhalt' => $schema->string()
                ->description('Text der öffentlichen Kundennotiz, so wie er im Ticket erscheinen soll.')
                ->nullable(),
            'body' => $schema->string()
                ->description('Alias für inhalt.')
                ->nullable(),
            'nachricht' => $schema->string()
                ->description('Alias für inhalt.')
                ->nullable(),
            'text' => $schema->string()
                ->description('Alias für inhalt.')
                ->nullable(),
        ];
    }

    private function resolveAndReply(
        ZammadTicketService $ticketService,
        Authenticatable $user,
        ?int $ticketId,
        string $ticketNumber,
        string $body,
    ): int {
        if ($ticketId === null) {
            $ticketId = $this->requireTicketIdByNumber($ticketService, $user, $ticketNumber);
            $ticketService->replyToTicket($user, $ticketId, $body);

            return $ticketId;
        }

        try {
            $ticketService->replyToTicket($user, $ticketId, $body);

            return $ticketId;
        } catch (RuntimeException $exception) {
            if (! $this->isMissingTicket($exception)) {
                throw $exception;
            }

            $lookup = $ticketNumber !== '' ? $ticketNumber : (string) $ticketId;
            $resolvedId = $this->findTicketIdByNumber($ticketService, $user, $lookup);

            if ($resolvedId === null || $resolvedId === $ticketId) {
                throw $exception;
            }

            $ticketService->replyToTicket($user, $resolvedId, $body);

            return $resolvedId;
        }
    }

    private function requireTicketIdByNumber(ZammadTicketService $ticketService, Authenticatable $user, string $ticketNumber): int
    {
        $ticketId = $this->findTicketIdByNumber($ticketService, $user, $ticketNumber);

        if ($ticketId === null) {
            throw new RuntimeException('Zur Ticketnummer '.$ticketNumber.' wurde kein Ticket gefunden, in dem Sie der Kunde sind.');
        }

        return $ticketId;
    }

    private function findTicketIdByNumber(ZammadTicketService $ticketService, Authenticatable $user, string $ticketNumber): ?int
    {
        $matches = $ticketService->listTicketsForUser(
            user: $user,
            filter: TicketFilterEnum::All,
            search: $ticketNumber,
        )->filter(fn (array $ticket): bool => (string) ($ticket['number'] ?? '') === $ticketNumber)
            ->values();

        if ($matches->count() > 1) {
            throw new RuntimeException('Zur Ticketnummer '.$ticketNumber.' gibt es mehrere Treffer. Bitte ticket_id aus ticket_detail_anzeigen verwenden.');
        }

        $match = $matches->first();

        if (! is_array($match) || ! isset($match['id'])) {
            return null;
        }

        return (int) $match['id'];
    }

    private function isMissingTicket(RuntimeException $exception): bool
    {
        return str_contains($exception->getMessage(), 'Ticket not found or access denied');
    }

    private function publicErrorMessage(RuntimeException $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'Ticket not found or access denied')) {
            return 'Ticket nicht gefunden oder kein Zugriff. Öffentliche Notizen sind nur in Tickets möglich, in denen Sie der Kunde sind.';
        }

        if (str_contains($message, 'Zammad customer mapping not found')) {
            return 'Für Ihr Benutzerkonto ist kein Zammad-Kunde hinterlegt. Eine öffentliche Notiz kann deshalb nicht geschrieben werden.';
        }

        if (str_contains($message, 'Ticketnummer')) {
            return $message;
        }

        return 'Die öffentliche Notiz konnte nicht gespeichert werden.';
    }

    /**
     * @param  array<string, mixed>|null  $ticket
     * @return array<string, mixed>
     */
    private function formatResult(int $ticketId, ?array $ticket, string $body): array
    {
        $number = isset($ticket['number']) ? (string) $ticket['number'] : (string) $ticketId;
        $url = route('apps.tickets.show', $ticketId);

        return [
            'message' => 'Die öffentliche Notiz wurde im Ticket gespeichert und ist für den Bearbeiter sichtbar.',
            'ticket_id' => $ticketId,
            'number' => $number,
            'title' => (string) ($ticket['title'] ?? ''),
            'state' => (string) ($ticket['state'] ?? ''),
            'url' => $url,
            'url_markdown' => sprintf('[Ticket #%s](%s)', $number, $url),
            'body' => $body,
        ];
    }
}
