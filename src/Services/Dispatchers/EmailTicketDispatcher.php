<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services\Dispatchers;

use Hwkdo\IntranetAppTickets\Mail\TicketCreatedMail;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailTicketDispatcher
{
    public function dispatch(TicketRequest $ticketRequest): void
    {
        $email = $ticketRequest->category->email;

        if ($email === null || $email === '') {
            throw new RuntimeException('Für diese Kategorie ist keine E-Mail-Adresse konfiguriert.');
        }

        Mail::to($email)->queue(new TicketCreatedMail($ticketRequest));
    }
}
