<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Mcp\Tools;

use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
class TicketDetailAnzeigenTool extends Tool
{
    protected string $name = 'ticket_detail_anzeigen';

    protected string $description = 'Zeigt Details zu einem Ticket inkl. Artikeln und Metadaten. Standard: Zammad-Ticket per ticket_id. Für Genehmigungsanfragen type="request" mit request_id (Nummer aus Übersicht z. B. A-123).';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user instanceof Authenticatable) {
            return Response::error('Authentifizierung erforderlich.');
        }

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:zammad,request'],
            'ticket_id' => ['nullable', 'integer', 'min:1'],
            'request_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = (string) ($validated['type'] ?? 'zammad');

        Log::info('ticket_detail_anzeigen called', [
            'user_id' => $user->getAuthIdentifier(),
            'type' => $type,
            'ticket_id' => $validated['ticket_id'] ?? null,
            'request_id' => $validated['request_id'] ?? null,
        ]);

        if ($type === 'request') {
            return $this->showRequest($user, $validated['request_id'] ?? null);
        }

        return $this->showZammadTicket($user, $validated['ticket_id'] ?? null);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Ticket-Typ: "zammad" (Standard) oder "request" für Genehmigungsanfragen.')
                ->nullable(),
            'ticket_id' => $schema->integer()
                ->description('Zammad-Ticket-ID (bei type="zammad").')
                ->nullable(),
            'request_id' => $schema->integer()
                ->description('Interne Anfrage-ID (bei type="request", z. B. aus A-123 → 123).')
                ->nullable(),
        ];
    }

    private function showZammadTicket(Authenticatable $user, ?int $ticketId): Response|ResponseFactory
    {
        if ($ticketId === null) {
            return Response::error('Für Zammad-Tickets ist ticket_id erforderlich.');
        }

        $ticketService = app(ZammadTicketService::class);
        $ticket = $ticketService->getTicketForUser($user, $ticketId);

        if ($ticket === null) {
            return Response::error('Ticket nicht gefunden oder kein Zugriff.');
        }

        $articles = $ticketService->getPublicArticlesForUser($user, $ticketId);
        $ownerId = isset($ticket['owner_id']) ? (int) $ticket['owner_id'] : null;
        $owner = $ownerId !== null ? $ticketService->getZammadUser($ownerId) : null;
        $url = route('apps.tickets.show', $ticketId);

        return Response::structured([
            'type' => 'zammad',
            'ticket' => [
                'id' => (int) ($ticket['id'] ?? $ticketId),
                'number' => isset($ticket['number']) ? (string) $ticket['number'] : null,
                'title' => (string) ($ticket['title'] ?? ''),
                'state' => (string) ($ticket['state'] ?? 'unbekannt'),
                'priority' => $ticket['priority'] ?? null,
                'group_id' => $ticket['group_id'] ?? null,
                'created_at' => $ticket['created_at'] ?? null,
                'updated_at' => $ticket['updated_at'] ?? null,
                'owner' => $owner !== null ? [
                    'id' => $owner['id'] ?? null,
                    'firstname' => $owner['firstname'] ?? null,
                    'lastname' => $owner['lastname'] ?? null,
                    'email' => $owner['email'] ?? null,
                ] : null,
                'url' => $url,
                'url_markdown' => sprintf('[Ticket #%s](%s)', $ticket['number'] ?? $ticketId, $url),
            ],
            'articles' => collect($articles)->map(fn (array $article): array => [
                'id' => $article['id'] ?? null,
                'sender' => $article['sender'] ?? null,
                'from' => $article['from'] ?? null,
                'subject' => $article['subject'] ?? null,
                'created_at' => $article['created_at'] ?? null,
                'body' => $article['body'] ?? '',
                'body_plain' => trim(strip_tags((string) ($article['body'] ?? ''))),
                'attachments' => collect($article['attachments'] ?? [])
                    ->map(fn (array $attachment): array => [
                        'id' => $attachment['id'] ?? null,
                        'filename' => $attachment['filename'] ?? null,
                        'size' => $attachment['size'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ])->values()->all(),
            'articles_count' => count($articles),
        ]);
    }

    private function showRequest(Authenticatable $user, ?int $requestId): Response|ResponseFactory
    {
        if ($requestId === null) {
            return Response::error('Für Genehmigungsanfragen ist request_id erforderlich.');
        }

        $ticketRequest = TicketRequest::query()
            ->with(['category', 'requester', 'onBehalfOf', 'attachments'])
            ->find($requestId);

        if ($ticketRequest === null) {
            return Response::error('Ticketanfrage nicht gefunden.');
        }

        if (! Gate::forUser($user)->allows('view', $ticketRequest)) {
            return Response::error('Kein Zugriff auf diese Ticketanfrage.');
        }

        $url = route('apps.tickets.requests.show', $ticketRequest);

        return Response::structured([
            'type' => 'request',
            'request' => [
                'id' => $ticketRequest->id,
                'number' => 'A-'.$ticketRequest->id,
                'subject' => $ticketRequest->subject,
                'status' => $ticketRequest->status->value,
                'status_label' => $ticketRequest->status->label(),
                'category' => $ticketRequest->category?->label,
                'category_slug' => $ticketRequest->category?->slug,
                'body' => $ticketRequest->body,
                'form_data' => $ticketRequest->form_data,
                'created_at' => $ticketRequest->created_at?->toIso8601String(),
                'updated_at' => $ticketRequest->updated_at?->toIso8601String(),
                'dispatched_at' => $ticketRequest->dispatched_at?->toIso8601String(),
                'zammad_ticket_id' => $ticketRequest->zammad_ticket_id,
                'zammad_url' => $ticketRequest->zammad_ticket_id !== null
                    ? route('apps.tickets.show', $ticketRequest->zammad_ticket_id)
                    : null,
                'rejection_reason' => $ticketRequest->rejection_reason,
                'dispatch_error' => $ticketRequest->dispatch_error,
                'requester' => $this->userSummary($ticketRequest->requester),
                'on_behalf_of' => $this->userSummary($ticketRequest->onBehalfOf),
                'url' => $url,
                'url_markdown' => sprintf('[Anfrage A-%d](%s)', $ticketRequest->id, $url),
            ],
            'attachments' => $ticketRequest->attachments
                ->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'filename' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userSummary(mixed $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => method_exists($user, 'getAttribute') ? $user->getAttribute('name') : null,
            'email' => method_exists($user, 'getAttribute') ? $user->getAttribute('email') : null,
        ];
    }
}
