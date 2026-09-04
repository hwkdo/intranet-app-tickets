<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets;

use Hwkdo\IntranetAppBase\Data\ManualDefinition;
use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Data\SearchActionDefinition;
use Hwkdo\IntranetAppBase\Data\TourDefinition;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesManualsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesNotificationsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesSearchActionsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesToursInterface;
use Hwkdo\IntranetAppTickets\Data\AppSettings;
use Hwkdo\IntranetAppTickets\Mcp\Servers\TicketsServer;
use Hwkdo\IntranetAppTickets\Tasks\PendingApprovalsTaskProvider;
use Hwkdo\IntranetAppTickets\Tasks\UnreadTicketsTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppTickets implements IntranetAppInterface, ProvidesManualsInterface, ProvidesNotificationsInterface, ProvidesSearchActionsInterface, ProvidesTasksInterface, ProvidesToursInterface
{
    public static function app_name(): string
    {
        return 'Tickets';
    }

    public static function app_icon(): string
    {
        return 'ticket';
    }

    public static function identifier(): string
    {
        return 'tickets';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-tickets.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-tickets.roles.user'));
    }

    public static function userSettingsClass(): ?string
    {
        return null;
    }

    public static function appSettingsClass(): ?string
    {
        return AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [
            'tickets' => [
                'class' => TicketsServer::class,
                'middleware' => ['auth:api'],
            ],
        ];
    }

    public static function taskProviders(): array
    {
        return [
            UnreadTicketsTaskProvider::class,
            PendingApprovalsTaskProvider::class,
        ];
    }

    public static function notificationTypes(): array
    {
        return [
            new NotificationTypeDefinition(
                key: 'tickets.pending_approval',
                label: 'Ticket zur Freigabe',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Benachrichtigung für Freigeber bei neuer Ticketanfrage.',
                mandatory: true,
            ),
            new NotificationTypeDefinition(
                key: 'tickets.approved',
                label: 'Ticket genehmigt',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Ihre Ticketanfrage wurde genehmigt.',
                mandatory: true,
            ),
            new NotificationTypeDefinition(
                key: 'tickets.rejected',
                label: 'Ticket abgelehnt',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Ihre Ticketanfrage wurde abgelehnt.',
                mandatory: false,
                defaultEnabled: true,
            ),
        ];
    }

    public static function tours(): array
    {
        return [
            new TourDefinition(
                key: 'tickets.onboarding',
                title: 'Tickets – Einstieg',
                description: 'Übersicht, Updates, Ticket-Details und neues Ticket erstellen – inkl. Demo-Daten für neue Nutzer.',
                group: 'app',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                routeName: 'apps.tickets.index',
                stepsModule: 'tickets/onboarding',
                sort: 100,
                version: 1,
            ),
            new TourDefinition(
                key: 'tickets.it-gestuetzte-pruefung',
                title: 'IT-gestützte Prüfung – Ticket anlegen',
                description: 'Schritt für Schritt: Prüfungsdatum wählen, Termine/Räume auswählen und Ticket-Daten ergänzen.',
                group: 'app',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                routeName: 'apps.tickets.create.form',
                routePath: 'apps/tickets/create/it-gestuetzte-pruefung',
                stepsModule: 'tickets/it-gestuetzte-pruefung',
                sort: 110,
                version: 1,
                mandatory: true,
            ),
        ];
    }

    public static function manuals(): array
    {
        return [
            new ManualDefinition(
                key: 'tickets.onboarding',
                title: 'Tickets-App – Bedienungsanleitung',
                description: 'Schritt-für-Schritt-Anleitung zur Tickets-App: Übersicht, Updates, Detailseite und neues Ticket.',
                group: 'app',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                contentView: 'intranet-app-tickets::manuals.onboarding.index',
                relatedTourKey: 'tickets.onboarding',
                sort: 100,
                version: 1,
                isPrimary: true,
            ),
        ];
    }

    public static function searchActions(): array
    {
        return [
            new SearchActionDefinition(
                key: 'tickets.create',
                title: 'Neues Ticket',
                keywords: ['ticket erstellen', 'neues ticket', 'ticket anlegen', 'ticket aufmachen'],
                routeName: 'apps.tickets.create.index',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                icon: self::app_icon(),
                permission: 'see-app-tickets',
                subtitle: self::app_name(),
                sort: 100,
            ),
        ];
    }
}
