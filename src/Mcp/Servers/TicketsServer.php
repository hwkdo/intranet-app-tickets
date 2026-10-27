<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Mcp\Servers;

use Hwkdo\IntranetAppBase\Mcp\Tools\BenutzerSuchenTool;
use Hwkdo\IntranetAppTickets\Mcp\Tools\TicketDetailAnzeigenTool;
use Hwkdo\IntranetAppTickets\Mcp\Tools\TicketErstellenTool;
use Hwkdo\IntranetAppTickets\Mcp\Tools\TicketNotizSchreibenTool;
use Hwkdo\IntranetAppTickets\Mcp\Tools\TicketsAnzeigenTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

class TicketsServer extends Server
{
    protected string $name = 'Tickets Server';

    protected string $version = '1.0.0';

    protected string $instructions = 'Dieser Server verwaltet Support-Tickets (Zammad) im Intranet. Workflow: 1) tickets_anzeigen mit filter="offen" (alle nicht geschlossenen) oder filter="alle", optional search für Volltext/Ticketnummer. 2) ticket_detail_anzeigen mit ticket_id für Zammad-Tickets; für Genehmigungsanfragen type="request" und request_id. 3) ticket_erstellen mit category_slug (z. B. it-support, hausmeisterservice, druckauftrag), betreff und inhalt; bei Ticket für andere Person optional on_behalf_of_user_id nach benutzer_suchen. Kategorien mit Genehmigungspflicht (z. B. webchange, marketing) erzeugen eine Anfrage zur Freigabe statt eines sofortigen Zammad-Tickets. 4) ticket_notiz_schreiben, wenn der Nutzer in einem bestehenden Ticket antworten soll: öffentliche Kundennotiz mit ticket_id (Feld id) oder ticket_number (sichtbare Nummer) und inhalt. Nur Tickets, in denen der angemeldete Nutzer der Kunde ist. Keine internen Notizen und nicht für Genehmigungsanfragen.';

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        BenutzerSuchenTool::class,
        TicketsAnzeigenTool::class,
        TicketDetailAnzeigenTool::class,
        TicketErstellenTool::class,
        TicketNotizSchreibenTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
