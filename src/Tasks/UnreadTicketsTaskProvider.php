<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Tasks;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppTickets\IntranetAppTickets;
use Hwkdo\IntranetAppTickets\Models\TicketReadState;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class UnreadTicketsTaskProvider implements TaskProviderInterface
{
    public function __construct(
        private readonly TicketReadStateService $readStateService,
    ) {}

    /**
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection
    {
        return $this->readStateService->unreadForUser($user)
            ->map(fn (TicketReadState $state) => new TaskItem(
                title: 'Ticket #'.$state->ticket_number,
                url: route('apps.tickets.show', $state->zammad_ticket_id),
                appIdentifier: IntranetAppTickets::identifier(),
                appName: IntranetAppTickets::app_name(),
                appIcon: IntranetAppTickets::app_icon(),
                description: $state->ticket_title,
                badge: 'Neu',
                priority: 10,
            ));
    }

    public function getLabel(): string
    {
        return 'Ungelesene Ticket-Updates';
    }
}
