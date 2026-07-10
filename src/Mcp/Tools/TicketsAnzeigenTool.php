<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Mcp\Tools;

use Hwkdo\IntranetAppTickets\Data\TicketListItem;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Services\TicketListService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
class TicketsAnzeigenTool extends Tool
{
    protected string $name = 'tickets_anzeigen';

    protected string $description = 'Zeigt eine Übersicht der Tickets des angemeldeten Nutzers. filter="offen" = alle nicht geschlossenen (neu, offen, wartend, …); filter="alle" = ohne Statusfilter. Optional Volltextsuche über search (Titel, Inhalt oder Ticketnummer).';

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user instanceof Authenticatable) {
            return Response::error('Authentifizierung erforderlich.');
        }

        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'in:offen,alle,open,all'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filter = $this->resolveFilter((string) ($validated['filter'] ?? 'offen'));
        $search = isset($validated['search']) ? trim((string) $validated['search']) : null;
        $search = $search === '' ? null : $search;
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : 50;

        Log::info('tickets_anzeigen called', [
            'user_id' => $user->getAuthIdentifier(),
            'filter' => $filter->value,
            'search' => $search,
            'limit' => $limit,
        ]);

        $zammadMapped = app(ZammadUserResolver::class)->resolveCustomerId($user) !== null;

        $tickets = $this->collectTickets(
            app(TicketListService::class),
            $user,
            $filter,
            $search,
            $limit,
        );

        return Response::structured([
            'filter' => $filter->value,
            'search' => $search,
            'zammad_mapped' => $zammadMapped,
            'total' => $tickets->count(),
            'tickets' => $tickets->map(fn (TicketListItem $item): array => [
                ...$item->toArray(),
                'url_markdown' => sprintf(
                    '[%s](%s)',
                    $item->number !== null ? 'Ticket #'.$item->number : $item->title,
                    $item->url,
                ),
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description('Statusfilter: "offen" = alle nicht geschlossenen Tickets (Standard); "alle" = alle Tickets inkl. geschlossener.')
                ->nullable(),
            'search' => $schema->string()
                ->description('Optionaler Suchbegriff (Titel, Artikelinhalt oder Ticketnummer).')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Maximale Anzahl zurückgegebener Einträge (Standard: 50, Maximum: 100).')
                ->nullable(),
        ];
    }

    private function resolveFilter(string $filter): TicketFilterEnum
    {
        return match ($filter) {
            'alle', 'all' => TicketFilterEnum::All,
            default => TicketFilterEnum::Open,
        };
    }

    /**
     * @return Collection<int, TicketListItem>
     */
    private function collectTickets(
        TicketListService $ticketListService,
        Authenticatable $user,
        TicketFilterEnum $filter,
        ?string $search,
        int $limit,
    ): Collection {
        $items = collect();
        $page = 1;

        do {
            $result = $ticketListService->fetchNextBatch(
                user: $user,
                filter: $filter,
                zammadPage: $page,
                includeRequests: $page === 1,
                existingItems: $items,
                search: $search,
            );

            $items = $items->merge($result->newItems);
            $page++;
        } while ($result->hasMore && $items->count() < $limit);

        return $items->take($limit)->values();
    }
}
