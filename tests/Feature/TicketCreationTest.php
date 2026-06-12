<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Database\Seeders\TicketCategorySeeder;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Mail\TicketCreatedMail;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TicketApprovalService;
use Hwkdo\IntranetAppTickets\Services\TicketListService;
use Hwkdo\IntranetAppTickets\Services\TicketSubmissionService;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TicketCategorySeeder::class);

    Permission::findOrCreate('see-app-tickets');
    Permission::findOrCreate('manage-app-tickets');
});

function ticketsTestUser(): User
{
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('see-app-tickets');

    return $user;
}

test('it ticket without approval is dispatched via zammad', function () {
    $user = ticketsTestUser();

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(555);
    });

    $request = app(TicketSubmissionService::class)->submit(
        category: $category,
        formData: [
            'betreff' => 'Test IT',
            'inhalt' => 'Beschreibung',
            'on_behalf_of_user_id' => $user->id,
        ],
        files: [],
        requester: $user,
        onBehalfOf: $user,
    );

    expect($request->fresh()->status)->toBe(TicketRequestStatus::Dispatched)
        ->and($request->zammad_ticket_id)->toBe(555);
});

test('moodle ticket sends email and does not call zammad', function () {
    Mail::fake();

    $user = ticketsTestUser();

    $category = TicketCategory::query()->where('slug', 'moodle')->firstOrFail();
    $category->update(['email' => 'moodle@example.com']);

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldNotReceive('createTicket');
    });

    $request = app(TicketSubmissionService::class)->submit(
        category: $category,
        formData: [
            'betreff' => 'Moodle Test',
            'inhalt' => 'Beschreibung',
            'on_behalf_of_user_id' => $user->id,
        ],
        files: [],
        requester: $user,
        onBehalfOf: $user,
    );

    expect($request->fresh()->status)->toBe(TicketRequestStatus::Dispatched);

    Mail::assertQueued(TicketCreatedMail::class, function (TicketCreatedMail $mail) use ($request) {
        return $mail->ticketRequest->is($request->fresh());
    });
});

test('marketing ticket stays pending until approved', function () {
    $user = ticketsTestUser();
    $approver = User::factory()->create(['active' => true]);
    $role = Role::create(['name' => 'Ticket-Marketing-Genehmiger', 'guard_name' => 'web']);
    $approver->assignRole($role);

    $category = TicketCategory::query()->where('slug', 'marketing')->firstOrFail();
    $category->update(['zammad_group_id' => 2]);
    $category->approverRoles()->sync([$role->id]);

    $request = app(TicketSubmissionService::class)->submit(
        category: $category,
        formData: [
            'betreff' => 'Flyer',
            'inhalt' => 'Marketing Inhalt',
            'art' => 'Neuanlage',
            'geschaeftsbereich' => 'GB',
            'fachbereich' => 'FB',
            'auswahl' => 'Unternehmer',
            'datum' => now()->addWeeks(13)->toDateString(),
            'abgestimmt_mit' => $approver->id,
            'jahresplanung' => 'Ja',
        ],
        files: [],
        requester: $user,
    );

    expect($request->status)->toBe(TicketRequestStatus::Pending);

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(777);
    });

    app(TicketApprovalService::class)->approve($request, $approver);

    expect($request->fresh()->status)->toBe(TicketRequestStatus::Dispatched)
        ->and($request->zammad_ticket_id)->toBe(777);
});

test('reject keeps request with rejected status', function () {
    $user = ticketsTestUser();
    $approver = User::factory()->create(['active' => true]);
    $role = Role::create(['name' => 'Ticket-Web-Genehmiger', 'guard_name' => 'web']);
    $approver->assignRole($role);

    $category = TicketCategory::query()->where('slug', 'webchange')->firstOrFail();
    $category->approverRoles()->sync([$role->id]);

    $request = TicketRequest::query()->create([
        'ticket_category_id' => $category->id,
        'requested_by_user_id' => $user->id,
        'subject' => 'Web Test',
        'body' => '<p>Test</p>',
        'form_data' => [],
        'status' => TicketRequestStatus::Pending,
    ]);

    app(TicketApprovalService::class)->reject($request, $approver, 'Nicht abgestimmt');

    expect($request->fresh()->status)->toBe(TicketRequestStatus::Rejected)
        ->and($request->rejection_reason)->toBe('Nicht abgestimmt');
});

test('user without approver role cannot approve', function () {
    $user = ticketsTestUser();
    $intruder = User::factory()->create(['active' => true]);

    $category = TicketCategory::query()->where('slug', 'marketing')->firstOrFail();
    $role = Role::create(['name' => 'Ticket-Marketing-Only', 'guard_name' => 'web']);
    $category->approverRoles()->sync([$role->id]);

    $request = TicketRequest::query()->create([
        'ticket_category_id' => $category->id,
        'requested_by_user_id' => $user->id,
        'subject' => 'Pending',
        'body' => '<p>Test</p>',
        'form_data' => [],
        'status' => TicketRequestStatus::Pending,
    ]);

    expect(app(TicketApprovalService::class)->userCanApproveRequest($intruder, $request))->toBeFalse();
});

test('pending request appears in ticket list with zur genehmigung badge', function () {
    $user = ticketsTestUser();

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();

    TicketRequest::query()->create([
        'ticket_category_id' => $category->id,
        'requested_by_user_id' => $user->id,
        'subject' => 'Offene Anfrage',
        'body' => '<p>Test</p>',
        'form_data' => [],
        'status' => TicketRequestStatus::Pending,
    ]);

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(null);
    });

    $items = app(TicketListService::class)->listForUser($user, TicketFilterEnum::Open);

    expect($items)->toHaveCount(1)
        ->and($items->first()->badge)->toBe('Zur Genehmigung');
});

test('moodle category uses email transmission by default', function () {
    $category = TicketCategory::query()->where('slug', 'moodle')->firstOrFail();

    expect($category->transmission)->toBe(TransmissionChannel::Email)
        ->and($category->requires_approval)->toBeFalse();
});

test('it support create form loads active employees', function () {
    $user = ticketsTestUser();
    User::factory()->create(['active' => true, 'vorname' => 'Zara', 'nachname' => 'Zulu']);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();

    $this->actingAs($user)
        ->get(route('apps.tickets.create.form', $category))
        ->assertSuccessful()
        ->assertSee('Zara Zulu');
});

test('ticket request show page renders for requester', function () {
    $user = ticketsTestUser();
    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();

    $request = TicketRequest::query()->create([
        'ticket_category_id' => $category->id,
        'requested_by_user_id' => $user->id,
        'subject' => 'IT Anfrage Test',
        'body' => '<p>Beschreibung</p>',
        'form_data' => [],
        'status' => TicketRequestStatus::Dispatched,
        'dispatched_at' => now(),
        'zammad_ticket_id' => 123,
    ]);

    $this->actingAs($user)
        ->get(route('apps.tickets.requests.show', $request))
        ->assertSuccessful()
        ->assertSee('IT Anfrage Test')
        ->assertSee('#A-'.$request->id);
});
