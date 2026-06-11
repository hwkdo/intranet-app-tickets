<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services\Dispatchers;

use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TicketUserZammadTagResolver;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

class ZammadTicketDispatcher
{
    public function __construct(
        private readonly ZammadTicketService $zammadTicketService,
        private readonly ZammadUserResolver $userResolver,
        private readonly TicketUserZammadTagResolver $tagResolver,
    ) {}

    public function dispatch(TicketRequest $ticketRequest): int
    {
        $category = $ticketRequest->category;

        if ($category->zammad_group_id === null) {
            throw new RuntimeException('Für diese Kategorie ist keine Zammad-Gruppe konfiguriert.');
        }

        $customer = $this->resolveCustomer($ticketRequest);

        $attachmentPayload = $ticketRequest->attachments
            ->map(fn ($attachment): array => [
                'filename' => $attachment->original_name,
                'data' => base64_encode($attachment->contents()),
                'mime-type' => $attachment->mime_type ?? 'application/octet-stream',
            ])
            ->all();

        $ticketId = $this->zammadTicketService->createTicket(
            customer: $customer,
            groupId: (int) $category->zammad_group_id,
            title: $ticketRequest->subject,
            body: $ticketRequest->body,
            attachments: $attachmentPayload,
        );

        $this->zammadTicketService->addTagsToTicket(
            $ticketId,
            $this->tagResolver->resolveForUser($customer),
        );

        return $ticketId;
    }

    private function resolveCustomer(TicketRequest $ticketRequest): Authenticatable
    {
        $user = $ticketRequest->onBehalfOf ?? $ticketRequest->requester;

        if ($this->userResolver->resolveCustomerId($user) === null) {
            throw new RuntimeException('Für den Ticket-Kunden wurde kein Zammad-Benutzer gefunden.');
        }

        return $user;
    }
}
