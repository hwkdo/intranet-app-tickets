<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Models\IntranetAppTicketsSettings;
use Hwkdo\IntranetAppTickets\Services\TicketsAppSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('updateZammadIntranetUserRoleId persists configured role', function (): void {
    IntranetAppTicketsSettings::query()->create([
        'version' => 1,
        'settings' => [
            'maxTicketsPerPage' => 50,
            'zammadIntranetUserRoleId' => null,
        ],
    ]);

    app(TicketsAppSettingsStore::class)->updateZammadIntranetUserRoleId(9);

    expect(app(TicketsAppSettingsStore::class)->zammadIntranetUserRoleId())->toBe(9)
        ->and(app(TicketsAppSettingsStore::class)->current()->maxTicketsPerPage)->toBe(50);
});
