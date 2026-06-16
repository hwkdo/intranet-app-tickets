<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Carbon\Carbon;
use Hwkdo\IntranetAppTickets\Data\TicketListItem;
use Hwkdo\IntranetAppTickets\Data\TicketListPageResult;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Enums\TicketListItemType;
use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TicketListService
{
    public function __construct(
        private readonly ZammadTicketService $zammadTicketService,
        private readonly ZammadUserResolver $userResolver,
        private readonly TicketReadStateService $readStateService,
    ) {}

    /**
     * @return Collection<int, TicketListItem>
     */
    public function listForUser(Authenticatable $user, TicketFilterEnum $filter, ?string $search = null): Collection
    {
        $items = collect();
        $page = 1;
        $perPage = $this->perPage();

        do {
            $result = $this->fetchNextBatch(
                user: $user,
                filter: $filter,
                zammadPage: $page,
                includeRequests: $page === 1,
                existingItems: $items,
                search: $search,
            );

            $items = $items->merge($result->newItems);
            $page++;
        } while ($result->hasMore);

        return $items->values();
    }

    /**
     * @param  Collection<int, TicketListItem>  $existingItems
     */
    public function fetchNextBatch(
        Authenticatable $user,
        TicketFilterEnum $filter,
        int $zammadPage,
        bool $includeRequests,
        Collection $existingItems,
        ?int $perPage = null,
        ?string $search = null,
    ): TicketListPageResult {
        $perPage ??= $this->perPage();
        $batch = collect();
        $normalizedSearch = $this->normalizeSearchTerm($search);

        if ($includeRequests) {
            $batch = $batch->merge($this->requestsForUser($user, $filter, $normalizedSearch));
        }

        $zammadCount = 0;

        if ($this->userResolver->resolveCustomerId($user) !== null) {
            $zammadTickets = $this->zammadTicketService->listTicketsForUser(
                $user,
                $filter,
                $zammadPage,
                $perPage,
                $normalizedSearch,
            );

            $zammadCount = $zammadTickets->count();
            $batch = $batch->merge($this->mapZammadTickets($user, $zammadTickets));
        }

        $existingKeys = $existingItems
            ->mapWithKeys(fn (TicketListItem $item): array => [$this->itemKey($item) => true]);

        $newItems = $batch
            ->reject(fn (TicketListItem $item): bool => $existingKeys->has($this->itemKey($item)))
            ->sortByDesc(fn (TicketListItem $item): int => $item->updatedAt?->timestamp ?? 0)
            ->values();

        return new TicketListPageResult(
            newItems: $newItems->all(),
            hasMore: $zammadCount === $perPage,
        );
    }

    private function perPage(): int
    {
        return max(1, (int) config('intranet-app-tickets.list_per_page', 15));
    }

    private function normalizeSearchTerm(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }

    private function itemKey(TicketListItem $item): string
    {
        return $item->type->value.':'.$item->id;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $zammadTickets
     * @return Collection<int, TicketListItem>
     */
    private function mapZammadTickets(Authenticatable $user, Collection $zammadTickets): Collection
    {
        $unreadIds = $this->readStateService->unreadForUser($user)
            ->pluck('zammad_ticket_id')
            ->all();

        return $zammadTickets->map(function (array $ticket) use ($unreadIds): TicketListItem {
            $isUnread = in_array($ticket['id'], $unreadIds, true);

            return new TicketListItem(
                type: TicketListItemType::Zammad,
                id: (int) $ticket['id'],
                number: isset($ticket['number']) ? (string) $ticket['number'] : null,
                title: (string) ($ticket['title'] ?? 'Ohne Titel'),
                statusLabel: (string) ($ticket['state'] ?? 'unbekannt'),
                updatedAt: isset($ticket['updated_at']) ? Carbon::parse($ticket['updated_at']) : null,
                url: route('apps.tickets.show', $ticket['id']),
                isUnread: $isUnread,
                badge: $isUnread ? 'Neu' : null,
            );
        });
    }

    /**
     * @return Collection<int, TicketListItem>
     */
    private function requestsForUser(Authenticatable $user, TicketFilterEnum $filter, ?string $search = null): Collection
    {
        $query = TicketRequest::query()
            ->with('category')
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('requested_by_user_id', $user->getAuthIdentifier())
                    ->orWhere('on_behalf_of_user_id', $user->getAuthIdentifier());
            });

        if ($search !== null) {
            $likeTerm = '%'.$search.'%';

            $query->where(function (Builder $builder) use ($likeTerm): void {
                $builder->where('subject', 'like', $likeTerm)
                    ->orWhere('body', 'like', $likeTerm);
            });
        }

        if ($filter === TicketFilterEnum::Open) {
            $query->where('status', TicketRequestStatus::Pending);
        } elseif ($filter === TicketFilterEnum::Closed) {
            $query->whereIn('status', [
                TicketRequestStatus::Rejected,
                TicketRequestStatus::Dispatched,
                TicketRequestStatus::Failed,
            ]);
        }

        return $query
            ->latest('updated_at')
            ->get()
            ->map(function (TicketRequest $request): TicketListItem {
                $badge = match ($request->status) {
                    TicketRequestStatus::Pending => 'Zur Genehmigung',
                    TicketRequestStatus::Rejected => 'Abgelehnt',
                    TicketRequestStatus::Failed => 'Fehlgeschlagen',
                    default => null,
                };

                return new TicketListItem(
                    type: TicketListItemType::Request,
                    id: $request->id,
                    number: 'A-'.$request->id,
                    title: $request->subject,
                    statusLabel: $request->status->label(),
                    updatedAt: $request->updated_at,
                    url: route('apps.tickets.requests.show', $request),
                    badge: $badge,
                );
            });
    }
}
