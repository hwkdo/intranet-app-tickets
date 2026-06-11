<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use ZammadAPIClient\Client;

class ZammadClientFactory
{
    public function make(): Client
    {
        return new Client([
            'url' => config('intranet-app-tickets.zammad.url'),
            'http_token' => config('intranet-app-tickets.zammad.http_token'),
            'debug' => config('intranet-app-tickets.zammad.debug'),
        ]);
    }
}
