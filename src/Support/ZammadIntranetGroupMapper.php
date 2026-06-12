<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Support;

use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Illuminate\Support\Collection;

class ZammadIntranetGroupMapper
{
    /**
     * @return Collection<string, Collection<int, TicketCategory>>
     */
    public function categoriesByGroupId(): Collection
    {
        return TicketCategory::query()
            ->where('transmission', TransmissionChannel::Zammad)
            ->whereNotNull('zammad_group_id')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy(fn (TicketCategory $category): string => (string) $category->zammad_group_id);
    }
}
