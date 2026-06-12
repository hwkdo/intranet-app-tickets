<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\Dispatchers\EmailTicketDispatcher;
use Hwkdo\IntranetAppTickets\Services\Dispatchers\ZammadTicketDispatcher;
use Throwable;

class TicketDispatchService
{
    public function __construct(
        private readonly ZammadTicketDispatcher $zammadDispatcher,
        private readonly EmailTicketDispatcher $emailDispatcher,
    ) {}

    public function dispatch(TicketRequest $ticketRequest): void
    {
        $ticketRequest->loadMissing(['category', 'attachments', 'requester', 'onBehalfOf']);

        if (! $ticketRequest->category->isConfiguredForDispatch()) {
            $ticketRequest->update([
                'status' => TicketRequestStatus::Failed,
                'dispatch_error' => 'Die Kategorie ist nicht vollständig konfiguriert.',
            ]);

            return;
        }

        try {
            $zammadTicketId = null;

            if ($ticketRequest->category->transmission === TransmissionChannel::Zammad) {
                $zammadTicketId = $this->zammadDispatcher->dispatch($ticketRequest);
            } else {
                $this->emailDispatcher->dispatch($ticketRequest);
            }

            $ticketRequest->update([
                'status' => TicketRequestStatus::Dispatched,
                'dispatched_at' => now(),
                'zammad_ticket_id' => $zammadTicketId,
                'dispatch_error' => null,
            ]);
        } catch (Throwable $exception) {
            $ticketRequest->update([
                'status' => $this->statusAfterDispatchFailure($ticketRequest),
                'dispatch_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function statusAfterDispatchFailure(TicketRequest $ticketRequest): TicketRequestStatus
    {
        if (in_array($ticketRequest->status, [TicketRequestStatus::Approved, TicketRequestStatus::Failed], true)
            && $ticketRequest->approved_at !== null) {
            return TicketRequestStatus::Approved;
        }

        return TicketRequestStatus::Failed;
    }
}
