<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Database\Seeders\TicketCategorySeeder;
use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TicketApprovalService;
use Hwkdo\IntranetAppTickets\Services\TicketDispatchService;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TicketCategorySeeder::class);
});

test('dispatch failure after approval keeps approved status for retry', function (): void {
    $user = User::factory()->create(['active' => true]);
    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $request = TicketRequest::query()->create([
        'ticket_category_id' => $category->id,
        'requested_by_user_id' => $user->id,
        'subject' => 'Test',
        'body' => 'Inhalt',
        'form_data' => [],
        'status' => TicketRequestStatus::Approved,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andThrow(new RuntimeException('Not authorized'));
    });

    try {
        app(TicketDispatchService::class)->dispatch($request->fresh(['category']));
    } catch (RuntimeException) {
        // expected
    }

    $request->refresh();

    expect($request->status)->toBe(TicketRequestStatus::Approved)
        ->and($request->dispatch_error)->toBe('Not authorized')
        ->and(app(TicketApprovalService::class)->canRetryDispatch($request))->toBeTrue();
});
