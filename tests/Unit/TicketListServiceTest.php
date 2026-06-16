<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Hwkdo\IntranetAppTickets\Data\TicketListItem;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Enums\TicketListItemType;
use Hwkdo\IntranetAppTickets\Services\TicketListService;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;

test('fetchNextBatch returns only new items and indicates more pages', function (): void {
    config(['intranet-app-tickets.list_per_page' => 2]);

    $user = User::factory()->make(['id' => 1]);

    $resolver = Mockery::mock(ZammadUserResolver::class);
    $resolver->shouldReceive('resolveCustomerId')->andReturn(42);

    $readState = Mockery::mock(TicketReadStateService::class);
    $readState->shouldReceive('unreadForUser')->andReturn(collect());

    $zammad = Mockery::mock(ZammadTicketService::class);
    $zammad->shouldReceive('listTicketsForUser')
        ->once()
        ->with($user, TicketFilterEnum::Closed, 1, 2, null)
        ->andReturn(collect([
            ['id' => 10, 'number' => '10010', 'title' => 'Ticket A', 'state' => 'closed', 'updated_at' => '2026-06-10T10:00:00Z'],
            ['id' => 11, 'number' => '10011', 'title' => 'Ticket B', 'state' => 'closed', 'updated_at' => '2026-06-09T10:00:00Z'],
        ]));

    $service = new TicketListService($zammad, $resolver, $readState);

    $firstPage = $service->fetchNextBatch(
        user: $user,
        filter: TicketFilterEnum::Closed,
        zammadPage: 1,
        includeRequests: false,
        existingItems: collect(),
    );

    expect($firstPage->newItems)->toHaveCount(2)
        ->and($firstPage->hasMore)->toBeTrue()
        ->and($firstPage->newItems[0])->toBeInstanceOf(TicketListItem::class)
        ->and($firstPage->newItems[0]->id)->toBe(10);

    $existing = collect($firstPage->newItems);

    $zammad->shouldReceive('listTicketsForUser')
        ->once()
        ->with($user, TicketFilterEnum::Closed, 2, 2, null)
        ->andReturn(collect([
            ['id' => 12, 'number' => '10012', 'title' => 'Ticket C', 'state' => 'closed', 'updated_at' => '2026-06-08T10:00:00Z'],
        ]));

    $secondPage = $service->fetchNextBatch(
        user: $user,
        filter: TicketFilterEnum::Closed,
        zammadPage: 2,
        includeRequests: false,
        existingItems: $existing,
    );

    expect($secondPage->newItems)->toHaveCount(1)
        ->and($secondPage->newItems[0]->id)->toBe(12)
        ->and($secondPage->hasMore)->toBeFalse();
});

test('fetchNextBatch skips duplicate items when merging batches', function (): void {
    config(['intranet-app-tickets.list_per_page' => 15]);

    $user = User::factory()->make(['id' => 1]);

    $resolver = Mockery::mock(ZammadUserResolver::class);
    $resolver->shouldReceive('resolveCustomerId')->andReturn(42);

    $readState = Mockery::mock(TicketReadStateService::class);
    $readState->shouldReceive('unreadForUser')->andReturn(collect());

    $zammad = Mockery::mock(ZammadTicketService::class);
    $zammad->shouldReceive('listTicketsForUser')
        ->once()
        ->with($user, TicketFilterEnum::Open, 2, 15, null)
        ->andReturn(collect([
            ['id' => 10, 'number' => '10010', 'title' => 'Ticket A', 'state' => 'open', 'updated_at' => '2026-06-10T10:00:00Z'],
        ]));

    $service = new TicketListService($zammad, $resolver, $readState);

    $existing = collect([
        new TicketListItem(
            type: TicketListItemType::Zammad,
            id: 10,
            number: '10010',
            title: 'Ticket A',
            statusLabel: 'open',
            updatedAt: Carbon::parse('2026-06-10T10:00:00Z'),
            url: '/apps/tickets/10',
        ),
    ]);

    $result = $service->fetchNextBatch(
        user: $user,
        filter: TicketFilterEnum::Open,
        zammadPage: 2,
        includeRequests: false,
        existingItems: $existing,
    );

    expect($result->newItems)->toBeEmpty();
});

test('ticket list item roundtrips through array serialization', function (): void {
    $item = new TicketListItem(
        type: TicketListItemType::Zammad,
        id: 99,
        number: '30099',
        title: 'Alpha & Beta',
        statusLabel: 'open',
        updatedAt: Carbon::parse('2026-06-10T12:00:00Z'),
        url: '/apps/tickets/99',
        isUnread: true,
        badge: 'Neu',
    );

    $restored = TicketListItem::fromArray($item->toArray());

    expect($restored->type)->toBe(TicketListItemType::Zammad)
        ->and($restored->title)->toBe('Alpha & Beta')
        ->and($restored->badge)->toBe('Neu');
});
