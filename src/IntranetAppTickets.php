<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppTickets\Data\AppSettings;
use Hwkdo\IntranetAppTickets\Data\UserSettings;
use Hwkdo\IntranetAppTickets\Tasks\UnreadTicketsTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppTickets implements IntranetAppInterface, ProvidesTasksInterface
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
        return UserSettings::class;
    }

    public static function appSettingsClass(): ?string
    {
        return AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    public static function taskProviders(): array
    {
        return [
            UnreadTicketsTaskProvider::class,
        ];
    }
}
