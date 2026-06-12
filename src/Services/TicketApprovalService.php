<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Notifications\TicketRequestApprovedNotification;
use Hwkdo\IntranetAppTickets\Notifications\TicketRequestRejectedNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class TicketApprovalService
{
    public function __construct(
        private readonly TicketDispatchService $dispatchService,
    ) {}

    public function userCanApproveAny(Authenticatable $user): bool
    {
        $roleIds = $user->roles()->pluck('id');

        if ($roleIds->isEmpty()) {
            return false;
        }

        return TicketCategory::query()
            ->where('requires_approval', true)
            ->where('active', true)
            ->whereHas('approverRoles', fn (Builder $query) => $query->whereIn('roles.id', $roleIds))
            ->exists();
    }

    public function userCanApproveRequest(Authenticatable $user, TicketRequest $ticketRequest): bool
    {
        if ($ticketRequest->status !== TicketRequestStatus::Pending) {
            return false;
        }

        return $this->userHasApproverRoleForCategory($user, $ticketRequest);
    }

    public function userCanRetryDispatch(Authenticatable $user, TicketRequest $ticketRequest): bool
    {
        if (! $this->canRetryDispatch($ticketRequest)) {
            return false;
        }

        return $this->userHasApproverRoleForCategory($user, $ticketRequest);
    }

    public function canRetryDispatch(TicketRequest $ticketRequest): bool
    {
        if ($ticketRequest->zammad_ticket_id !== null || $ticketRequest->dispatched_at !== null) {
            return false;
        }

        if ($ticketRequest->dispatch_error === null || $ticketRequest->dispatch_error === '') {
            return false;
        }

        return in_array($ticketRequest->status, [TicketRequestStatus::Approved, TicketRequestStatus::Failed], true)
            && $ticketRequest->approved_at !== null;
    }

    private function userHasApproverRoleForCategory(Authenticatable $user, TicketRequest $ticketRequest): bool
    {
        $roleIds = $user->roles()->pluck('id');

        $ticketRequest->loadMissing('category');

        return $ticketRequest->category
            ->approverRoles()
            ->whereIn('roles.id', $roleIds)
            ->exists();
    }

    /**
     * @return Collection<int, TicketRequest>
     */
    public function pendingRequestsForUser(Authenticatable $user): Collection
    {
        $roleIds = $user->roles()->pluck('id');

        return TicketRequest::query()
            ->with(['category', 'requester', 'onBehalfOf'])
            ->where('status', TicketRequestStatus::Pending)
            ->whereHas('category.approverRoles', fn (Builder $query) => $query->whereIn('roles.id', $roleIds))
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, Authenticatable>
     */
    public function approverUsersForRequest(TicketRequest $ticketRequest): Collection
    {
        $ticketRequest->loadMissing('category.approverRoles.users');

        return $ticketRequest->category->approverRoles
            ->flatMap(fn ($role) => $role->users)
            ->unique('id')
            ->values();
    }

    public function approve(TicketRequest $ticketRequest, Authenticatable $approver, ?string $note = null): TicketRequest
    {
        if (! $this->userCanApproveRequest($approver, $ticketRequest)) {
            throw new RuntimeException('Sie sind nicht berechtigt, diese Anfrage zu genehmigen.');
        }

        $ticketRequest->update([
            'status' => TicketRequestStatus::Approved,
            'approved_by_user_id' => $approver->getAuthIdentifier(),
            'approved_at' => now(),
            'approval_note' => $note,
        ]);

        $ticketRequest = $ticketRequest->fresh(['category', 'attachments', 'requester', 'onBehalfOf']);

        try {
            $this->dispatchService->dispatch($ticketRequest);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Die Anfrage wurde genehmigt, die Übermittlung an Zammad ist jedoch fehlgeschlagen: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        $ticketRequest->requester?->notify(new TicketRequestApprovedNotification($ticketRequest->fresh()));

        return $ticketRequest->fresh(['category', 'attachments', 'requester', 'onBehalfOf', 'approver']);
    }

    public function retryDispatch(TicketRequest $ticketRequest, Authenticatable $approver): TicketRequest
    {
        if (! $this->userCanRetryDispatch($approver, $ticketRequest)) {
            throw new RuntimeException('Sie sind nicht berechtigt, diese Anfrage erneut zu übermitteln.');
        }

        $ticketRequest->update([
            'status' => TicketRequestStatus::Approved,
            'dispatch_error' => null,
        ]);

        $ticketRequest = $ticketRequest->fresh(['category', 'attachments', 'requester', 'onBehalfOf']);

        try {
            $this->dispatchService->dispatch($ticketRequest);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Die erneute Übermittlung an Zammad ist fehlgeschlagen: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        $ticketRequest->requester?->notify(new TicketRequestApprovedNotification($ticketRequest->fresh()));

        return $ticketRequest->fresh(['category', 'attachments', 'requester', 'onBehalfOf', 'approver']);
    }

    public function reject(TicketRequest $ticketRequest, Authenticatable $approver, string $reason): TicketRequest
    {
        if (! $this->userCanApproveRequest($approver, $ticketRequest)) {
            throw new RuntimeException('Sie sind nicht berechtigt, diese Anfrage abzulehnen.');
        }

        $ticketRequest->update([
            'status' => TicketRequestStatus::Rejected,
            'approved_by_user_id' => $approver->getAuthIdentifier(),
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $ticketRequest->requester?->notify(new TicketRequestRejectedNotification($ticketRequest->fresh()));

        return $ticketRequest->fresh(['category', 'attachments', 'requester', 'onBehalfOf', 'approver']);
    }
}
