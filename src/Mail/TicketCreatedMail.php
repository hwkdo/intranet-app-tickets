<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Mail;

use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly TicketRequest $ticketRequest,
    ) {}

    public function envelope(): Envelope
    {
        $customer = $this->ticketRequest->onBehalfOf ?? $this->ticketRequest->requester;

        return new Envelope(
            from: new Address(
                (string) $customer->email,
                (string) ($customer->name ?? $customer->email),
            ),
            subject: $this->ticketRequest->subject,
        );
    }

    public function content(): Content
    {
        $customer = $this->ticketRequest->onBehalfOf ?? $this->ticketRequest->requester;

        return new Content(
            view: 'intranet-app-tickets::mail.ticket-created',
            with: [
                'kategorie' => $this->ticketRequest->category->label,
                'betreff' => $this->ticketRequest->subject,
                'name' => $customer->name ?? '',
                'email' => $customer->email ?? '',
                'inhalt' => $this->ticketRequest->body,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->ticketRequest->attachments
            ->map(function ($attachment): Attachment {
                return Attachment::fromStorageDisk($attachment->disk, $attachment->path)
                    ->as($attachment->original_name)
                    ->withMime($attachment->mime_type ?? 'application/octet-stream');
            })
            ->all();
    }
}
