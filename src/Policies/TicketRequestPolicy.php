<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Policies;

use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TicketApprovalService;
use Illuminate\Contracts\Auth\Authenticatable;

class TicketRequestPolicy
{
    public function __construct(
        private readonly TicketApprovalService $approvalService,
    ) {}

    public function view(Authenticatable $user, TicketRequest $ticketRequest): bool
    {
        if ((int) $ticketRequest->requested_by_user_id === (int) $user->getAuthIdentifier()) {
            return true;
        }

        if ((int) $ticketRequest->on_behalf_of_user_id === (int) $user->getAuthIdentifier()) {
            return true;
        }

        return $this->approvalService->userCanApproveRequest($user, $ticketRequest);
    }

    public function approve(Authenticatable $user, TicketRequest $ticketRequest): bool
    {
        return $this->approvalService->userCanApproveRequest($user, $ticketRequest);
    }

    public function reject(Authenticatable $user, TicketRequest $ticketRequest): bool
    {
        return $this->approvalService->userCanApproveRequest($user, $ticketRequest);
    }
}
