<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Mcp\Servers\TicketsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/apps/tickets', TicketsServer::class)
    ->middleware(['auth:api']);
