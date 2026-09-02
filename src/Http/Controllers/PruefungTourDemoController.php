<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Http\Controllers;

use Hwkdo\IntranetAppTickets\Support\PruefungTourDemo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PruefungTourDemoController
{
    public function enable(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('see-app-tickets'), 403);

        PruefungTourDemo::enable();

        return response()->json(['ok' => true, 'demo' => true]);
    }

    public function disable(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('see-app-tickets'), 403);

        PruefungTourDemo::disable();

        return response()->json(['ok' => true, 'demo' => false]);
    }
}
