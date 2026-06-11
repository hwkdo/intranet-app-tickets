<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;

class AppSettings extends BaseAppSettings
{
    public function __construct(
        #[Description('Maximale Anzahl von Tickets pro API-Abfrage')]
        public int $maxTicketsPerPage = 100,
    ) {}
}
