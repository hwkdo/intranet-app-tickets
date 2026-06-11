<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Enums\TicketFormType;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Notifications\PendingTicketApprovalNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class TicketSubmissionService
{
    public function __construct(
        private readonly TicketBodyBuilder $bodyBuilder,
        private readonly TicketAttachmentStorage $attachmentStorage,
        private readonly TicketDispatchService $dispatchService,
        private readonly TicketApprovalService $approvalService,
        private readonly TicketFormValidation $formValidation,
    ) {}

    /**
     * @param  array<string, mixed>  $formData
     * @param  list<UploadedFile>  $files
     */
    public function submit(
        TicketCategory $category,
        array $formData,
        array $files,
        Authenticatable $requester,
        ?Authenticatable $onBehalfOf = null,
        ?Authenticatable $supervisor = null,
        ?string $standortName = null,
        ?string $ansprechpartnerName = null,
    ): TicketRequest {
        if (! $category->active) {
            throw new RuntimeException('Diese Ticketkategorie ist derzeit nicht verfügbar.');
        }

        if ($category->form instanceof TicketFormType) {
            $formData = $this->formValidation->filterFormData($category->form, $formData);
        }

        $baseContent = (string) ($formData['inhalt'] ?? $formData['beschreibung'] ?? '');
        $betreff = (string) ($formData['betreff'] ?? $formData['subject'] ?? 'Ticket');
        $betreff2 = isset($formData['betreff2']) ? (string) $formData['betreff2'] : null;

        $body = $this->bodyBuilder->build(
            baseContent: $baseContent,
            formData: $formData,
            requester: $requester,
            onBehalfOf: $onBehalfOf,
            supervisor: $supervisor,
            standortName: $standortName,
            ansprechpartnerName: $ansprechpartnerName,
        );

        $ticketRequest = TicketRequest::query()->create([
            'ticket_category_id' => $category->id,
            'requested_by_user_id' => $requester->getAuthIdentifier(),
            'on_behalf_of_user_id' => $onBehalfOf?->getAuthIdentifier(),
            'subject' => $this->bodyBuilder->buildSubject($betreff, $betreff2),
            'body' => $body,
            'form_data' => $formData,
            'status' => $category->requires_approval
                ? TicketRequestStatus::Pending
                : TicketRequestStatus::Approved,
        ]);

        if ($category->requires_approval) {
            $ticketRequest->update([
                'subject' => $this->bodyBuilder->buildSubject($betreff, $betreff2, $ticketRequest->id),
            ]);
        }

        if ($files !== []) {
            $this->attachmentStorage->storeForRequest($ticketRequest, $files);
        }

        if ($category->requires_approval) {
            $this->notifyApprovers($ticketRequest);

            return $ticketRequest->fresh(['category', 'attachments', 'requester', 'onBehalfOf']);
        }

        $this->dispatchService->dispatch($ticketRequest->fresh(['category', 'attachments', 'requester', 'onBehalfOf']));

        return $ticketRequest->fresh(['category', 'attachments', 'requester', 'onBehalfOf']);
    }

    private function notifyApprovers(TicketRequest $ticketRequest): void
    {
        $ticketRequest->loadMissing(['category.approverRoles.users']);

        $users = $this->approvalService->approverUsersForRequest($ticketRequest);

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new PendingTicketApprovalNotification($ticketRequest));
    }
}
