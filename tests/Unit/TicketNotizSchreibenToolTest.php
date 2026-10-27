<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Mcp\Servers\TicketsServer;
use Hwkdo\IntranetAppTickets\Mcp\Tools\TicketNotizSchreibenTool;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Testing\Fluent\AssertableJson;
use RuntimeException;

test('ticket_notiz_schreiben speichert eine öffentliche notiz im kundenticket', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('replyToTicket')
            ->once()
            ->with(Mockery::on(fn (Authenticatable $customer): bool => (int) $customer->getAuthIdentifier() === 7), 42, 'Ich freue mich auf den Termin.');
        $mock->shouldReceive('getTicketForUser')
            ->once()
            ->andReturn([
                'id' => 42,
                'number' => '3152005',
                'title' => 'Büro umziehen',
                'state' => 'open',
            ]);
        $mock->shouldNotReceive('listTicketsForUser');
    });

    $response = TicketsServer::actingAs($user)->tool(TicketNotizSchreibenTool::class, [
        'ticket_id' => 42,
        'inhalt' => 'Ich freue mich auf den Termin.',
    ]);

    $response->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('message', 'Die öffentliche Notiz wurde im Ticket gespeichert und ist für den Bearbeiter sichtbar.')
        ->where('ticket_id', 42)
        ->where('number', '3152005')
        ->where('title', 'Büro umziehen')
        ->where('state', 'open')
        ->where('body', 'Ich freue mich auf den Termin.')
        ->where('url', route('apps.tickets.show', 42))
        ->where('url_markdown', sprintf('[Ticket #%s](%s)', '3152005', route('apps.tickets.show', 42)))
    );
});

test('ticket_notiz_schreiben akzeptiert die sichtbare ticketnummer', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('listTicketsForUser')
            ->once()
            ->andReturn(collect([
                ['id' => 42, 'number' => '3152005', 'title' => 'Büro umziehen'],
                ['id' => 9, 'number' => '315200', 'title' => 'Anderes Ticket'],
            ]));
        $mock->shouldReceive('replyToTicket')
            ->once()
            ->with(Mockery::type(Authenticatable::class), 42, 'Termin passt.');
        $mock->shouldReceive('getTicketForUser')
            ->once()
            ->andReturn([
                'id' => 42,
                'number' => '3152005',
                'title' => 'Büro umziehen',
                'state' => 'open',
            ]);
    });

    TicketsServer::actingAs($user)->tool(TicketNotizSchreibenTool::class, [
        'ticket_number' => '3152005',
        'nachricht' => 'Termin passt.',
    ])->assertOk()->assertSee('3152005');
});

test('ticket_notiz_schreiben löst eine als id übergebene ticketnummer auf', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('replyToTicket')
            ->once()
            ->with(Mockery::type(Authenticatable::class), 3152005, 'Termin passt.')
            ->andThrow(new RuntimeException('Ticket not found or access denied.'));
        $mock->shouldReceive('listTicketsForUser')
            ->once()
            ->andReturn(collect([
                ['id' => 42, 'number' => '3152005'],
            ]));
        $mock->shouldReceive('replyToTicket')
            ->once()
            ->with(Mockery::type(Authenticatable::class), 42, 'Termin passt.');
        $mock->shouldReceive('getTicketForUser')
            ->once()
            ->andReturn([
                'id' => 42,
                'number' => '3152005',
                'title' => 'Büro umziehen',
                'state' => 'open',
            ]);
    });

    TicketsServer::actingAs($user)->tool(TicketNotizSchreibenTool::class, [
        'ticket_id' => 3152005,
        'body' => 'Termin passt.',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('ticket_id', 42)
        ->where('number', '3152005')
        ->etc()
    );
});

test('ticket_notiz_schreiben verweigert tickets fremder kunden', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('replyToTicket')
            ->once()
            ->andThrow(new RuntimeException('Ticket not found or access denied.'));
        $mock->shouldReceive('listTicketsForUser')
            ->once()
            ->andReturn(collect());
        $mock->shouldNotReceive('getTicketForUser');
    });

    TicketsServer::actingAs($user)->tool(TicketNotizSchreibenTool::class, [
        'ticket_id' => 99,
        'inhalt' => 'Das sollte nicht ankommen.',
    ])->assertHasErrors([
        'Öffentliche Notizen sind nur in Tickets möglich, in denen Sie der Kunde sind.',
    ]);
});

test('ticket_notiz_schreiben meldet mehrdeutige ticketnummern', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('listTicketsForUser')
            ->once()
            ->andReturn(collect([
                ['id' => 1, 'number' => '100'],
                ['id' => 2, 'number' => '100'],
            ]));
        $mock->shouldNotReceive('replyToTicket');
    });

    TicketsServer::actingAs($user)->tool(TicketNotizSchreibenTool::class, [
        'ticket_number' => '100',
        'inhalt' => 'Hallo',
    ])->assertHasErrors([
        'Zur Ticketnummer 100 gibt es mehrere Treffer.',
    ]);
});

test('ticket_notiz_schreiben verlangt einen text', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldNotReceive('replyToTicket');
    });

    TicketsServer::actingAs($user)->tool(TicketNotizSchreibenTool::class, [
        'ticket_id' => 42,
        'inhalt' => '   ',
    ])->assertHasErrors([
        'Das Feld inhalt (oder body/nachricht) ist erforderlich.',
    ]);
});

test('ticket_notiz_schreiben verlangt eine authentifizierung', function (): void {
    TicketsServer::tool(TicketNotizSchreibenTool::class, [
        'ticket_id' => 42,
        'inhalt' => 'Hallo',
    ])->assertHasErrors([
        'Authentifizierung erforderlich.',
    ]);
});
