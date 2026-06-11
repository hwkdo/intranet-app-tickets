<?php

namespace Hwkdo\IntranetAppTickets\Commands;

use Illuminate\Console\Command;

class IntranetAppTicketsCommand extends Command
{
    public $signature = 'intranet-app-tickets';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
