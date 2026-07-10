<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Mcp\Tools\BenutzerSuchenTool;
use Hwkdo\IntranetAppTickets\IntranetAppTickets;
use Hwkdo\IntranetAppTickets\Mcp\Servers\TicketsServer;
use Hwkdo\IntranetAppTickets\Mcp\Tools\TicketDetailAnzeigenTool;
use Hwkdo\IntranetAppTickets\Mcp\Tools\TicketErstellenTool;
use Hwkdo\IntranetAppTickets\Mcp\Tools\TicketsAnzeigenTool;

it('registriert die MCP tools in der erwarteten Reihenfolge', function (): void {
    $server = new TicketsServer;
    $reflection = new ReflectionClass($server);
    $toolsProperty = $reflection->getProperty('tools');
    $toolsProperty->setAccessible(true);

    expect($toolsProperty->getValue($server))->toBe([
        BenutzerSuchenTool::class,
        TicketsAnzeigenTool::class,
        TicketDetailAnzeigenTool::class,
        TicketErstellenTool::class,
    ]);
});

it('hat klare server instructions fuer den ticket flow', function (): void {
    $server = new TicketsServer;
    $reflection = new ReflectionClass($server);
    $instructionsProperty = $reflection->getProperty('instructions');
    $instructionsProperty->setAccessible(true);
    $instructions = $instructionsProperty->getValue($server);

    expect($instructions)
        ->toContain('tickets_anzeigen')
        ->toContain('ticket_detail_anzeigen')
        ->toContain('ticket_erstellen')
        ->toContain('filter="offen"')
        ->toContain('benutzer_suchen');
});

it('stellt den tickets MCP server in der app konfiguration bereit', function (): void {
    expect(IntranetAppTickets::mcpServers())->toBe([
        'tickets' => [
            'class' => TicketsServer::class,
            'middleware' => ['auth:api'],
        ],
    ]);
});
