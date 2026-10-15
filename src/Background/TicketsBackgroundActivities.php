<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Background;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppTickets\IntranetAppTickets;
use Hwkdo\IntranetAppTickets\Jobs\CreateTicketFromTeamsMessageJob;
use Hwkdo\IntranetAppTickets\Mail\TicketCreatedMail;

final class TicketsBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        $id = IntranetAppTickets::identifier();
        $name = IntranetAppTickets::app_name();

        return [
            BackgroundActivityDefinition::queued(
                key: 'tickets.from-teams',
                label: 'Ticket aus Teams',
                appIdentifier: $id,
                appName: $name,
                job: CreateTicketFromTeamsMessageJob::class,
                sort: 10,
            ),
            BackgroundActivityDefinition::mail(
                key: 'tickets.mail-created',
                label: 'Mail: Ticket erstellt',
                appIdentifier: $id,
                appName: $name,
                mailable: TicketCreatedMail::class,
                sort: 20,
            ),
        ];
    }
}
