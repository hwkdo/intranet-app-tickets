<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppTickets\IntranetAppTickets;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class PendingTicketApprovalNotification extends IntranetNotification
{
    public function __construct(
        public readonly TicketRequest $ticketRequest,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'tickets.pending_approval';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Neue Ticket-Genehmigung: '.$this->ticketRequest->subject)
            ->line('Es liegt eine neue Ticketanfrage zur Genehmigung vor.')
            ->action('Genehmigungen öffnen', route('apps.tickets.approvals.show', $this->ticketRequest));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Ticket zur Freigabe: '.$this->ticketRequest->subject,
            body: 'Es liegt eine neue Ticketanfrage zur Genehmigung vor.',
            url: route('apps.tickets.approvals.show', $this->ticketRequest),
            appIdentifier: IntranetAppTickets::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Ticket zur Freigabe')
            ->body($this->ticketRequest->subject)
            ->data(['url' => route('apps.tickets.approvals.show', $this->ticketRequest)]);
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Neue Ticketanfrage zur Genehmigung: '.$this->ticketRequest->subject,
            'topic' => 'Tickets',
            'url' => route('apps.tickets.approvals.show', $this->ticketRequest),
        ];
    }
}
