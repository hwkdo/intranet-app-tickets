<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Data\AppSettings;
use Hwkdo\IntranetAppTickets\Models\IntranetAppTicketsSettings;

class TicketsAppSettingsStore
{
    public function current(): AppSettings
    {
        return IntranetAppTicketsSettings::current()?->settings ?? new AppSettings;
    }

    public function zammadIntranetUserRoleId(): ?int
    {
        return $this->current()->zammadIntranetUserRoleId;
    }

    public function save(AppSettings $settings): void
    {
        $model = IntranetAppTicketsSettings::current();

        if ($model !== null) {
            $model->update(['settings' => $settings]);

            return;
        }

        IntranetAppTicketsSettings::query()->create([
            'version' => 1,
            'settings' => $settings,
        ]);
    }

    public function updateZammadIntranetUserRoleId(?int $roleId): void
    {
        $current = $this->current();

        $this->save(new AppSettings(
            maxTicketsPerPage: $current->maxTicketsPerPage,
            zammadIntranetUserRoleId: $roleId,
            teamsTicketAiEnabled: $current->teamsTicketAiEnabled,
            teamsTicketAiProvider: $current->teamsTicketAiProvider,
            teamsTicketAiModelOpenWebUi: $current->teamsTicketAiModelOpenWebUi,
            teamsTicketAiModelLangdock: $current->teamsTicketAiModelLangdock,
        ));
    }
}
