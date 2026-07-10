<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Mcp\Tools;

use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TicketSubmissionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use RuntimeException;
use Throwable;

#[IsOpenWorld]
class TicketErstellenTool extends Tool
{
    protected string $name = 'ticket_erstellen';

    protected string $description = 'Erstellt ein neues Ticket in einer Kategorie (category_slug). Pflicht: betreff und inhalt. Optional on_behalf_of_user_id für Tickets im Namen einer anderen Person (zuerst benutzer_suchen). Kategorien mit Genehmigungspflicht erzeugen eine Anfrage zur Freigabe.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user instanceof Authenticatable) {
            return Response::error('Authentifizierung erforderlich.');
        }

        $validated = $request->validate([
            'category_slug' => ['required', 'string', 'max:255'],
            'betreff' => ['nullable', 'string', 'max:150'],
            'subject' => ['nullable', 'string', 'max:150'],
            'inhalt' => ['nullable', 'string', 'max:10000'],
            'body' => ['nullable', 'string', 'max:10000'],
            'beschreibung' => ['nullable', 'string', 'max:10000'],
            'on_behalf_of_user_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'category_slug.required' => 'Das Feld category_slug ist erforderlich (z. B. it-support).',
        ]);

        $betreff = trim((string) ($validated['betreff'] ?? $validated['subject'] ?? ''));
        $inhalt = trim((string) ($validated['inhalt'] ?? $validated['body'] ?? $validated['beschreibung'] ?? ''));

        if ($betreff === '') {
            return Response::error('Das Feld betreff (oder subject) ist erforderlich.');
        }

        if ($inhalt === '') {
            return Response::error('Das Feld inhalt (oder body/beschreibung) ist erforderlich.');
        }

        Log::info('ticket_erstellen called', [
            'user_id' => $user->getAuthIdentifier(),
            'category_slug' => $validated['category_slug'],
            'on_behalf_of_user_id' => $validated['on_behalf_of_user_id'] ?? null,
        ]);

        $category = TicketCategory::query()
            ->where('slug', $validated['category_slug'])
            ->where('active', true)
            ->first();

        if (! $category instanceof TicketCategory) {
            return Response::structured([
                'error' => 'Kategorie nicht gefunden oder inaktiv.',
                'category_slug' => $validated['category_slug'],
                'available_categories' => $this->availableCategories(),
            ]);
        }

        if (! $category->isConfiguredForDispatch() && ! $category->requires_approval) {
            return Response::error('Diese Kategorie ist nicht vollständig konfiguriert und kann derzeit nicht genutzt werden.');
        }

        $onBehalfOf = $this->resolveOnBehalfOfUser($validated['on_behalf_of_user_id'] ?? null);

        if (($validated['on_behalf_of_user_id'] ?? null) !== null && $onBehalfOf === null) {
            return Response::error('on_behalf_of_user_id wurde nicht gefunden. Bitte benutzer_suchen nutzen.');
        }

        $formData = [
            'betreff' => $betreff,
            'inhalt' => $this->composeBody($inhalt, $user),
        ];

        if ($onBehalfOf !== null) {
            $formData['on_behalf_of_user_id'] = $onBehalfOf->getAuthIdentifier();
        }

        try {
            $ticketRequest = app(TicketSubmissionService::class)->submit(
                category: $category,
                formData: $formData,
                files: [],
                requester: $user,
                onBehalfOf: $onBehalfOf,
            );
        } catch (RuntimeException $exception) {
            return Response::error($exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('ticket_erstellen failed', [
                'message' => $exception->getMessage(),
                'user_id' => $user->getAuthIdentifier(),
            ]);

            return Response::error('Beim Erstellen des Tickets ist ein Fehler aufgetreten.');
        }

        return Response::structured($this->formatResult($ticketRequest));
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category_slug' => $schema->string()
                ->description('Slug der Ticketkategorie, z. B. it-support, hausmeisterservice, druckauftrag.')
                ->required(),
            'betreff' => $schema->string()
                ->description('Betreff des Tickets.')
                ->nullable(),
            'subject' => $schema->string()
                ->description('Alias für betreff.')
                ->nullable(),
            'inhalt' => $schema->string()
                ->description('Beschreibung / Inhalt des Tickets.')
                ->nullable(),
            'body' => $schema->string()
                ->description('Alias für inhalt.')
                ->nullable(),
            'beschreibung' => $schema->string()
                ->description('Alias für inhalt.')
                ->nullable(),
            'on_behalf_of_user_id' => $schema->integer()
                ->description('Optional: Intranet-user_id einer anderen Person, für die das Ticket erstellt wird.')
                ->nullable(),
        ];
    }

    private function composeBody(string $inhalt, Authenticatable $user): string
    {
        $actorName = method_exists($user, 'getAttribute') ? (string) ($user->getAttribute('name') ?? '') : '';
        $actorEmail = method_exists($user, 'getAttribute') ? (string) ($user->getAttribute('email') ?? '') : '';

        $meta = array_filter([
            '---',
            'Erstellt über MCP',
            $actorName !== '' ? 'Ausgelöst von: '.$actorName.($actorEmail !== '' ? ' ('.$actorEmail.')' : '') : null,
            'Am: '.now()->format('d.m.Y H:i'),
        ]);

        return trim($inhalt."\n\n".implode("\n", $meta));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatResult(TicketRequest $ticketRequest): array
    {
        $ticketRequest->loadMissing(['category']);

        $result = [
            'request_id' => $ticketRequest->id,
            'request_number' => 'A-'.$ticketRequest->id,
            'subject' => $ticketRequest->subject,
            'status' => $ticketRequest->status->value,
            'status_label' => $ticketRequest->status->label(),
            'category' => $ticketRequest->category?->label,
            'category_slug' => $ticketRequest->category?->slug,
            'requires_approval' => (bool) $ticketRequest->category?->requires_approval,
            'url' => route('apps.tickets.requests.show', $ticketRequest),
        ];

        if ($ticketRequest->status === TicketRequestStatus::Pending) {
            $result['message'] = 'Die Ticketanfrage wurde erstellt und wartet auf Genehmigung.';
        } elseif ($ticketRequest->zammad_ticket_id !== null) {
            $zammadUrl = route('apps.tickets.show', $ticketRequest->zammad_ticket_id);
            $result['zammad_ticket_id'] = $ticketRequest->zammad_ticket_id;
            $result['zammad_url'] = $zammadUrl;
            $result['url_markdown'] = sprintf('[Ticket #%d](%s)', $ticketRequest->zammad_ticket_id, $zammadUrl);
            $result['message'] = 'Das Ticket wurde erstellt.';
        } elseif ($ticketRequest->category?->transmission === TransmissionChannel::Email) {
            $result['message'] = 'Die Ticketanfrage wurde per E-Mail übermittelt.';
        } else {
            $result['message'] = 'Die Ticketanfrage wurde erstellt.';
        }

        return $result;
    }

    /**
     * @return list<array{slug: string, label: string, requires_approval: bool}>
     */
    private function availableCategories(): array
    {
        return TicketCategory::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get(['slug', 'label', 'requires_approval'])
            ->map(fn (TicketCategory $category): array => [
                'slug' => $category->slug,
                'label' => $category->label,
                'requires_approval' => $category->requires_approval,
            ])
            ->values()
            ->all();
    }

    private function resolveOnBehalfOfUser(?int $userId): ?Authenticatable
    {
        if ($userId === null) {
            return null;
        }

        $userModel = config('intranet-app-tickets.user_model');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            return null;
        }

        $user = $userModel::query()->find($userId);

        return $user instanceof Authenticatable ? $user : null;
    }
}
