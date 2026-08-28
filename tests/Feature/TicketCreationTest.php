<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\BueLaravel\BueLaravel;
use Hwkdo\IntranetAppTickets\Database\Seeders\TicketCategorySeeder;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Enums\TicketFormType;
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
use Livewire\Volt\Volt;
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

test('it gestuetzte pruefung category is seeded without approval', function () {
    $category = TicketCategory::query()->where('slug', 'it-gestuetzte-pruefung')->firstOrFail();

    expect($category->form)->toBe(TicketFormType::ItGestuetztePruefung)
        ->and($category->label)->toBe('IT-gestützte Prüfung')
        ->and($category->transmission)->toBe(TransmissionChannel::Zammad)
        ->and($category->requires_approval)->toBeFalse()
        ->and($category->active)->toBeTrue();
});

test('it gestuetzte pruefung ticket is dispatched via zammad without approval', function () {
    $user = ticketsTestUser();

    $category = TicketCategory::query()->where('slug', 'it-gestuetzte-pruefung')->firstOrFail();
    $category->update(['zammad_group_id' => 42]);

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(888);
    });

    $request = app(TicketSubmissionService::class)->submit(
        category: $category,
        formData: [
            'betreff' => 'IT-gestützte Prüfung: FKB Sommer',
            'pruefungstermin_id' => 1247861,
            'pruefung_id' => 9001,
            'datum' => '2026-08-27',
            'gewerk' => 'Kaufmännische Betriebsführung',
            'raeume' => '1303, Bildungszentrum HWK Haus I',
            'anzahl_teilnehmer' => 10,
            'ansprechpartner' => 'Susanne Potthoff, 0231 5493-511',
            'verwendete_anwendungen' => 'Moodle, Word',
            'weitere_wichtige_informationen' => 'Namensschilder vorhanden',
            'sperre_pruefungsbenutzer_ab' => '2026-08-28 18:00',
        ],
        files: [],
        requester: $user,
    );

    expect($request->fresh()->status)->toBe(TicketRequestStatus::Dispatched)
        ->and($request->zammad_ticket_id)->toBe(888)
        ->and($request->form_data['pruefungstermin_id'])->toBe(1247861)
        ->and($request->form_data['pruefung_id'])->toBe(9001)
        ->and($request->body)->toContain('PrüfungID: 9001')
        ->and($request->body)->toContain('Verwendete Anwendungen: Moodle, Word')
        ->and($request->body)->toContain('Sperre Prüfungsbenutzer ab: 2026-08-28 18:00');
});

test('it gestuetzte pruefung create form loads termin list from bue', function () {
    $user = ticketsTestUser();
    $category = TicketCategory::query()->where('slug', 'it-gestuetzte-pruefung')->firstOrFail();

    $this->mock(BueLaravel::class, function ($mock): void {
        $mock->shouldReceive('getTicketPruefungenByDatum')
            ->once()
            ->andReturn(collect([(object) [
                'termin_id' => 1247861,
                'pruefung_id' => 9001,
                'pruefung_bezeichnung' => 'FKB VZ 7 Sommer 2026',
                'termin_bezeichnung' => 'schriftliche Prüfung EDV-gestützt',
                'ordnung' => 'Kaufmännische Betriebsführung',
                'uhrzeit_von' => '09:00',
                'uhrzeit_bis' => '10:00',
                'pruefungsort_name' => '1303',
                'gebaeudenummer' => 'Bildungszentrum HWK Haus I',
                'raumnummer' => null,
                'anzahl_prueflinge' => 10,
                'bearbeiter_vorname' => 'Susanne',
                'bearbeiter_nachname' => 'Potthoff',
                'bearbeiter_telefon' => '0231 5493-511',
                'bearbeiter_email' => 'susanne.potthoff@hwk-do.de',
                'datum' => '2026-08-27 00:00:00',
            ]]));
    });

    $this->actingAs($user);

    Volt::test('apps.tickets.create.form', ['category' => $category])
        ->assertSet('pruefung_step', 1)
        ->assertSee('Wann findet die Prüfung statt?')
        ->set('pruefung_datum', '2026-08-27')
        ->call('loadPruefungen')
        ->assertSet('pruefung_step', 2)
        ->assertSee('FKB VZ 7 Sommer 2026')
        ->call('togglePruefungTermin', 1247861)
        ->assertSet('pruefung_selected_ids', [1247861])
        ->call('confirmPruefungTermine')
        ->assertSet('pruefung_step', 3)
        ->assertSet('pruefungstermin_id', 1247861)
        ->assertSet('pruefung_id', 9001)
        ->assertSet('gewerk', 'Kaufmännische Betriebsführung')
        ->assertSet('raeume', '1303, Bildungszentrum HWK Haus I')
        ->assertSee('Verwendete Anwendungen');
});

test('it gestuetzte pruefung allows multi select only for same pruefung id', function () {
    $user = ticketsTestUser();
    $category = TicketCategory::query()->where('slug', 'it-gestuetzte-pruefung')->firstOrFail();

    $this->mock(BueLaravel::class, function ($mock): void {
        $mock->shouldReceive('getTicketPruefungenByDatum')
            ->once()
            ->andReturn(collect([
                (object) [
                    'termin_id' => 1001,
                    'pruefung_id' => 50,
                    'pruefung_bezeichnung' => 'Prüfung A',
                    'termin_bezeichnung' => 'Raum 1',
                    'ordnung' => 'Maler',
                    'uhrzeit_von' => '09:00',
                    'uhrzeit_bis' => '10:00',
                    'pruefungsort_name' => '1301',
                    'gebaeudenummer' => 'Haus I',
                    'raumnummer' => null,
                    'anzahl_prueflinge' => 8,
                    'bearbeiter_vorname' => 'Anna',
                    'bearbeiter_nachname' => 'Admin',
                    'bearbeiter_telefon' => '111',
                    'bearbeiter_email' => 'a@example.com',
                    'datum' => '2026-08-27 00:00:00',
                ],
                (object) [
                    'termin_id' => 1002,
                    'pruefung_id' => 50,
                    'pruefung_bezeichnung' => 'Prüfung A',
                    'termin_bezeichnung' => 'Raum 2',
                    'ordnung' => 'Maler',
                    'uhrzeit_von' => '09:00',
                    'uhrzeit_bis' => '10:00',
                    'pruefungsort_name' => '1302',
                    'gebaeudenummer' => 'Haus I',
                    'raumnummer' => null,
                    'anzahl_prueflinge' => 12,
                    'bearbeiter_vorname' => 'Anna',
                    'bearbeiter_nachname' => 'Admin',
                    'bearbeiter_telefon' => '111',
                    'bearbeiter_email' => 'a@example.com',
                    'datum' => '2026-08-27 00:00:00',
                ],
                (object) [
                    'termin_id' => 2001,
                    'pruefung_id' => 99,
                    'pruefung_bezeichnung' => 'Prüfung B',
                    'termin_bezeichnung' => 'Raum X',
                    'ordnung' => 'Tischler',
                    'uhrzeit_von' => '11:00',
                    'uhrzeit_bis' => '12:00',
                    'pruefungsort_name' => '2201',
                    'gebaeudenummer' => 'Haus II',
                    'raumnummer' => null,
                    'anzahl_prueflinge' => 5,
                    'bearbeiter_vorname' => 'Bert',
                    'bearbeiter_nachname' => 'Bearbeiter',
                    'bearbeiter_telefon' => '222',
                    'bearbeiter_email' => 'b@example.com',
                    'datum' => '2026-08-27 00:00:00',
                ],
            ]));
    });

    $this->actingAs($user);

    Volt::test('apps.tickets.create.form', ['category' => $category])
        ->set('pruefung_datum', '2026-08-27')
        ->call('loadPruefungen')
        ->call('togglePruefungTermin', 1001)
        ->assertSet('pruefung_selected_ids', [1001])
        ->assertSet('pruefung_selected_pruefung_id', 50)
        ->call('togglePruefungTermin', 2001)
        ->assertSet('pruefung_selected_ids', [1001])
        ->call('togglePruefungTermin', 1002)
        ->assertSet('pruefung_selected_ids', [1001, 1002])
        ->call('confirmPruefungTermine')
        ->assertSet('pruefung_step', 3)
        ->assertSet('betreff', 'IT-gestützte Prüfung: Prüfung A (2 Räume)')
        ->assertSet('pruefung_id', 50)
        ->assertSet('pruefungstermine', [
            [
                'pruefungstermin_id' => 1001,
                'raeume' => '1301, Haus I',
                'anzahl_teilnehmer' => 8,
            ],
            [
                'pruefungstermin_id' => 1002,
                'raeume' => '1302, Haus I',
                'anzahl_teilnehmer' => 12,
            ],
        ])
        ->assertSet('pruefungstermin_id', null);
});

test('it gestuetzte pruefung multi ticket body lists rooms separately', function () {
    $user = ticketsTestUser();

    $category = TicketCategory::query()->where('slug', 'it-gestuetzte-pruefung')->firstOrFail();
    $category->update(['zammad_group_id' => 42]);

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(889);
    });

    $request = app(TicketSubmissionService::class)->submit(
        category: $category,
        formData: [
            'betreff' => 'IT-gestützte Prüfung: Prüfung A (2 Räume)',
            'pruefung_id' => 50,
            'pruefungstermine' => [
                [
                    'pruefungstermin_id' => 1001,
                    'raeume' => '1301, Haus I',
                    'anzahl_teilnehmer' => 8,
                ],
                [
                    'pruefungstermin_id' => 1002,
                    'raeume' => '1302, Haus I',
                    'anzahl_teilnehmer' => 12,
                ],
            ],
            'datum' => '2026-08-27',
            'gewerk' => 'Maler',
            'ansprechpartner' => 'Anna Admin',
            'verwendete_anwendungen' => 'Moodle',
        ],
        files: [],
        requester: $user,
    );

    expect($request->fresh()->status)->toBe(TicketRequestStatus::Dispatched)
        ->and($request->subject)->toBe('IT-gestützte Prüfung: Prüfung A (2 Räume)')
        ->and($request->body)->toContain('Die Prüfung findet in 2 Räumen statt.')
        ->and($request->body)->toContain('PrüfungID: 50')
        ->and($request->body)->toContain('PrüfungsterminID: 1001')
        ->and($request->body)->toContain('Raum: 1301, Haus I')
        ->and($request->body)->toContain('Anzahl Teilnehmer: 8')
        ->and($request->body)->toContain('PrüfungsterminID: 1002')
        ->and($request->body)->toContain('Raum: 1302, Haus I')
        ->and($request->body)->toContain('Anzahl Teilnehmer: 12')
        ->and($request->body)->toContain('Gewerk (Ordnung): Maler')
        ->and($request->body)->toContain('Verwendete Anwendungen: Moodle')
        ->and($request->body)->not->toContain('Räume:')
        ->and($request->form_data)->toHaveKey('pruefungstermine')
        ->and($request->form_data['pruefung_id'])->toBe(50)
        ->and($request->form_data)->not->toHaveKey('pruefungstermin_id');
});
