<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Notifications;

use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingTicketApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TicketRequest $ticketRequest,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
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
        return [
            'ticket_request_id' => $this->ticketRequest->id,
            'subject' => $this->ticketRequest->subject,
            'type' => 'pending_approval',
        ];
    }
}
