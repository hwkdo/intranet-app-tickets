<?php

declare(strict_types=1);

use App\Models\Gvp;
use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppTickets\Database\Seeders\TicketCategorySeeder;
use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketGvpTag;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Models\TicketStandortTag;
use Hwkdo\IntranetAppTickets\Services\Dispatchers\ZammadTicketDispatcher;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TicketCategorySeeder::class);
});

test('dispatcher adds standort and gvp tags for ticket customer', function (): void {
    $standort = Standort::query()->create([
        'name' => 'Dortmund',
        'extension' => 'DO',
        'strasse' => 'Musterstraße 1',
        'ort' => 'Dortmund',
        'plz' => '44135',
    ]);
    $gvp = Gvp::factory()->create();

    TicketStandortTag::query()->create([
        'standort_id' => $standort->id,
        'tag' => 'standort-do',
    ]);

    TicketGvpTag::query()->create([
        'gvp_id' => $gvp->id,
        'tag' => 'gvp-it',
    ]);

    $customer = User::factory()->create([
        'active' => true,
        'standort_id' => $standort->id,
        'gvp_id' => $gvp->id,
    ]);

    $requester = User::factory()->create(['active' => true]);

    $category = TicketCategory::query()
        ->where('slug', 'it-support')
        ->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $ticketRequest = TicketRequest::query()->create([
        'ticket_category_id' => $category->id,
        'requested_by_user_id' => $requester->id,
        'on_behalf_of_user_id' => $customer->id,
        'subject' => 'Test',
        'body' => 'Inhalt',
        'form_data' => [],
        'status' => TicketRequestStatus::Approved,
    ]);

    $this->mock(ZammadUserResolver::class, function ($mock) use ($customer): void {
        $mock->shouldReceive('resolveCustomerId')->with($customer)->andReturn(42);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(999);
        $mock->shouldReceive('addTagsToTicket')
            ->once()
            ->with(999, ['standort-do', 'gvp-it']);
    });

    $ticketId = app(ZammadTicketDispatcher::class)->dispatch($ticketRequest->fresh(['category', 'requester', 'onBehalfOf']));

    expect($ticketId)->toBe(999);
});
