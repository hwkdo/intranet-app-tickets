<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Notifications;

use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRequestRejectedNotification extends Notification implements ShouldQueue
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
            ->subject('Ticketanfrage abgelehnt: '.$this->ticketRequest->subject)
            ->line('Ihre Ticketanfrage wurde abgelehnt.')
            ->line('Begründung: '.$this->ticketRequest->rejection_reason)
            ->action('Anfrage ansehen', route('apps.tickets.requests.show', $this->ticketRequest));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_request_id' => $this->ticketRequest->id,
            'subject' => $this->ticketRequest->subject,
            'type' => 'rejected',
            'rejection_reason' => $this->ticketRequest->rejection_reason,
        ];
    }
}
