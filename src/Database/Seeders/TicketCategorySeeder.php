<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Database\Seeders;

use Hwkdo\IntranetAppTickets\Enums\TicketFormType;
use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'it-support',
                'label' => 'IT-Support',
                'form' => TicketFormType::ItSupport,
                'transmission' => TransmissionChannel::Zammad,
                'requires_approval' => false,
                'legacy_id' => 2,
                'sort_order' => 10,
            ],
            [
                'slug' => 'hausmeisterservice',
                'label' => 'Hausmeisterservice',
                'form' => TicketFormType::Hausmeisterservice,
                'transmission' => TransmissionChannel::Zammad,
                'requires_approval' => false,
                'legacy_id' => 5,
                'sort_order' => 20,
            ],
            [
                'slug' => 'webchange',
                'label' => 'Webchange',
                'form' => TicketFormType::Webchange,
                'transmission' => TransmissionChannel::Zammad,
                'requires_approval' => true,
                'legacy_id' => 6,
                'sort_order' => 30,
            ],
            [
                'slug' => 'marketing',
                'label' => 'Marketing',
                'form' => TicketFormType::Marketing,
                'transmission' => TransmissionChannel::Zammad,
                'requires_approval' => true,
                'legacy_id' => 7,
                'sort_order' => 40,
            ],
            [
                'slug' => 'druckauftrag',
                'label' => 'Druckauftrag',
                'form' => TicketFormType::Druckauftrag,
                'transmission' => TransmissionChannel::Zammad,
                'requires_approval' => false,
                'legacy_id' => 8,
                'sort_order' => 50,
            ],
            [
                'slug' => 'vertragsmanagement',
                'label' => 'Vertragsmanagement',
                'form' => TicketFormType::Vertragsmanagement,
                'transmission' => TransmissionChannel::Zammad,
                'requires_approval' => false,
                'legacy_id' => 12,
                'sort_order' => 60,
            ],
            [
                'slug' => 'zollauktion',
                'label' => 'Zollauktion / Anlagenverkauf',
                'form' => TicketFormType::Zollauktion,
                'transmission' => TransmissionChannel::Zammad,
                'requires_approval' => false,
                'legacy_id' => 13,
                'sort_order' => 70,
                'active' => false,
            ],
            [
                'slug' => 'moodle',
                'label' => 'Moodle',
                'form' => TicketFormType::Moodle,
                'transmission' => TransmissionChannel::Email,
                'requires_approval' => false,
                'legacy_id' => 14,
                'sort_order' => 80,
            ],
        ];

        foreach ($categories as $data) {
            TicketCategory::query()->updateOrCreate(
                ['slug' => $data['slug']],
                array_merge([
                    'active' => true,
                    'zammad_group_id' => null,
                    'email' => null,
                ], $data),
            );
        }
    }
}
