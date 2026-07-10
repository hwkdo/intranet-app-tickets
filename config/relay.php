<?php

declare(strict_types=1);

use Prism\Relay\Enums\Transport;

return [
    'servers' => [
        'tickets' => [
            'transport' => Transport::Http,
            'url' => env('RELAY_TICKETS_SERVER_URL', 'http://localhost/mcp/apps/tickets'),
            'timeout' => env('RELAY_TICKETS_SERVER_TIMEOUT', 30),
            'headers' => [
                // Bearer Token wird dynamisch zur Laufzeit hinzugefügt.
            ],
        ],
    ],
];
