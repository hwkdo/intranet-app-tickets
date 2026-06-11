<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Carbon\Carbon;
use Hwkdo\IntranetAppTickets\Data\TicketListItem;
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
    public function listForUser(Authenticatable $user, TicketFilterEnum $filter): Collection
    {
        $items = collect();

        if ($this->userResolver->resolveCustomerId($user) !== null) {
            $zammadTickets = $this->zammadTicketService->listTicketsForUser($user, $filter);
            $unreadIds = $this->readStateService->unreadForUser($user)
                ->pluck('zammad_ticket_id')
                ->all();

            foreach ($zammadTickets as $ticket) {
                $items->push(new TicketListItem(
                    type: TicketListItemType::Zammad,
                    id: (int) $ticket['id'],
                    number: isset($ticket['number']) ? (string) $ticket['number'] : null,
                    title: (string) ($ticket['title'] ?? 'Ohne Titel'),
                    statusLabel: (string) ($ticket['state'] ?? 'unbekannt'),
                    updatedAt: isset($ticket['updated_at']) ? Carbon::parse($ticket['updated_at']) : null,
                    url: route('apps.tickets.show', $ticket['id']),
                    isUnread: in_array($ticket['id'], $unreadIds, true),
                    badge: in_array($ticket['id'], $unreadIds, true) ? 'Neu' : null,
                ));
            }
        }

        $requestItems = $this->requestsForUser($user, $filter);

        return $items
            ->merge($requestItems)
            ->sortByDesc(fn (TicketListItem $item) => $item->updatedAt?->timestamp ?? 0)
            ->values();
    }

    /**
     * @return Collection<int, TicketListItem>
     */
    private function requestsForUser(Authenticatable $user, TicketFilterEnum $filter): Collection
    {
        $query = TicketRequest::query()
            ->with('category')
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('requested_by_user_id', $user->getAuthIdentifier())
                    ->orWhere('on_behalf_of_user_id', $user->getAuthIdentifier());
            });

        if ($filter === TicketFilterEnum::Open) {
            $query->where('status', TicketRequestStatus::Pending);
        } elseif ($filter === TicketFilterEnum::Closed) {
            $query->whereIn('status', [
                TicketRequestStatus::Rejected,
                TicketRequestStatus::Dispatched,
                TicketRequestStatus::Failed,
            ]);
        }
        // TicketFilterEnum::All → keine Status-Einschränkung

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
