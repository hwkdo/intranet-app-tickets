<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Database\Seeders\TicketCategorySeeder;
use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Support\ZammadIntranetGroupMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('categoriesByGroupId maps zammad groups to intranet ticket categories', function (): void {
    $this->seed(TicketCategorySeeder::class);

    TicketCategory::query()
        ->where('slug', 'it-support')
        ->update([
            'transmission' => TransmissionChannel::Zammad,
            'zammad_group_id' => 1,
        ]);

    TicketCategory::query()
        ->where('slug', 'webchange')
        ->update([
            'transmission' => TransmissionChannel::Zammad,
            'zammad_group_id' => 4,
        ]);

    $mapped = app(ZammadIntranetGroupMapper::class)->categoriesByGroupId();

    expect($mapped->keys()->all())->toBe(['1', '4'])
        ->and($mapped->get('1')?->first()?->slug)->toBe('it-support')
        ->and($mapped->get('4')?->first()?->slug)->toBe('webchange');
});
