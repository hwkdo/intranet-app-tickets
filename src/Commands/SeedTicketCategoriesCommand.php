<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Commands;

use Hwkdo\IntranetAppTickets\Database\Seeders\TicketCategorySeeder;
use Illuminate\Console\Command;

class SeedTicketCategoriesCommand extends Command
{
    protected $signature = 'intranet-app-tickets:seed-categories';

    protected $description = 'Seed default ticket categories for the tickets app';

    public function handle(): int
    {
        (new TicketCategorySeeder)->run();

        $this->info('Ticket categories seeded.');

        return self::SUCCESS;
    }
}
