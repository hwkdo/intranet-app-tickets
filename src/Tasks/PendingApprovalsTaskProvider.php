<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Tasks;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppTickets\IntranetAppTickets;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TicketApprovalService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class PendingApprovalsTaskProvider implements TaskProviderInterface
{
    public function __construct(
        private readonly TicketApprovalService $approvalService,
    ) {}

    /**
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection
    {
        if (! $this->approvalService->userCanApproveAny($user)) {
            return collect();
        }

        return $this->approvalService->pendingRequestsForUser($user)
            ->map(fn (TicketRequest $request) => new TaskItem(
                title: 'Genehmigung: '.$request->subject,
                url: route('apps.tickets.approvals.show', $request),
                appIdentifier: IntranetAppTickets::identifier(),
                appName: IntranetAppTickets::app_name(),
                appIcon: IntranetAppTickets::app_icon(),
                description: $request->category->label,
                badge: 'Offen',
                priority: 20,
            ));
    }

    public function getLabel(): string
    {
        return 'Offene Ticket-Genehmigungen';
    }
}
