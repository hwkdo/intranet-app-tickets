<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Http\Controllers;

use Hwkdo\IntranetAppTickets\Support\TicketTourDemo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTourDemoController
{
    public function enable(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('see-app-tickets'), 403);

        TicketTourDemo::enable();

        return response()->json(['ok' => true, 'demo' => true]);
    }

    public function disable(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('see-app-tickets'), 403);

        TicketTourDemo::disable();

        return response()->json(['ok' => true, 'demo' => false]);
    }

    public function simulateUpdate(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('see-app-tickets'), 403);
        abort_unless(TicketTourDemo::isActive(), 404);

        TicketTourDemo::markFirstTicketUpdated();

        return response()->json([
            'ok' => true,
            'ticket_number' => (string) 100000,
            'ticket_id' => TicketTourDemo::DEMO_TICKET_ID_START,
        ]);
    }
}
