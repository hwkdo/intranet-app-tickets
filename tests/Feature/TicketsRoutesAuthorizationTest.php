<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Models\ZammadUserMapping;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-tickets', 'web');
    Permission::findOrCreate('manage-app-tickets', 'web');
});

test('tickets index is accessible with permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-tickets');

    ZammadUserMapping::query()->create([
        'user_id' => $user->id,
        'zammad_customer_id' => 8,
        'zammad_email' => $user->email,
        'resolved_at' => now(),
    ]);

    $this->mock(ZammadTicketService::class, function ($mock) {
        $mock->shouldReceive('listTicketsForUser')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 81,
                    'number' => '10081',
                    'title' => 'Test Ticket',
                    'state' => 'open',
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));
    });

    $this->actingAs($user)
        ->get(route('apps.tickets.index'))
        ->assertOk()
        ->assertSeeText('Test Ticket');
});

test('tickets index is forbidden without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('apps.tickets.index'))
        ->assertForbidden();
});

test('ticket show returns not found when ticket does not belong to user', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-tickets');

    ZammadUserMapping::query()->create([
        'user_id' => $user->id,
        'zammad_customer_id' => 8,
        'zammad_email' => $user->email,
        'resolved_at' => now(),
    ]);

    $this->mock(ZammadTicketService::class, function ($mock) {
        $mock->shouldReceive('getTicketForUser')
            ->once()
            ->andReturn(null);
        $mock->shouldReceive('getPublicArticlesForUser')->never();
    });

    $this->actingAs($user)
        ->get(route('apps.tickets.show', 81))
        ->assertNotFound();
});
