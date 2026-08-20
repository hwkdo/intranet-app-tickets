<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Notifications;

use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Hwkdo\IntranetAppTickets\IntranetAppTickets;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class TicketRequestRejectedNotification extends IntranetNotification
{
    public function __construct(
        public readonly TicketRequest $ticketRequest,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'tickets.rejected';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ticketanfrage abgelehnt: '.$this->ticketRequest->subject)
            ->line('Ihre Ticketanfrage wurde abgelehnt.')
            ->action('Anfrage ansehen', route('apps.tickets.requests.show', $this->ticketRequest));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->inboxPayload(
            title: 'Ticket abgelehnt: '.$this->ticketRequest->subject,
            body: 'Ihre Ticketanfrage wurde abgelehnt.',
            url: route('apps.tickets.requests.show', $this->ticketRequest),
            appIdentifier: IntranetAppTickets::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Ticket abgelehnt')
            ->body($this->ticketRequest->subject)
            ->data(['url' => route('apps.tickets.requests.show', $this->ticketRequest)]);
    }

    public function toTeams(object $notifiable): array
    {
        return [
            'preview' => 'Ihre Ticketanfrage wurde abgelehnt: '.$this->ticketRequest->subject,
            'topic' => 'Tickets',
            'url' => route('apps.tickets.requests.show', $this->ticketRequest),
        ];
    }
}
