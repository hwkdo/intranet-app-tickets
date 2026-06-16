<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Support;

use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;

class ZammadTicketSearchQueryBuilder
{
    public function build(int $customerId, TicketFilterEnum $filter, ?string $search = null): string
    {
        $query = 'customer_id:'.$customerId;

        if ($filter === TicketFilterEnum::Open) {
            foreach (config('intranet-app-tickets.closed_state_ids', [4, 5]) as $stateId) {
                $query .= ' AND !(state_id:'.(int) $stateId.')';
            }
        }

        if ($filter === TicketFilterEnum::Closed) {
            $conditions = collect(config('intranet-app-tickets.closed_state_ids', [4, 5]))
                ->map(fn (int $stateId): string => 'state_id:'.$stateId)
                ->implode(' OR ');

            $query .= ' AND ('.$conditions.')';
        }

        $term = $this->normalizeSearchTerm($search);

        if ($term !== null) {
            $query .= ' AND ('.$this->buildSearchTermClause($term).')';
        }

        return $query;
    }

    public function normalizeSearchTerm(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }

    private function buildSearchTermClause(string $term): string
    {
        if (preg_match('/^\d+$/', $term) === 1) {
            return 'number:'.$this->escapeUnquotedValue($term).'*';
        }

        $quoted = $this->quoteValue($term);

        return 'title:'.$quoted.' OR article.body:'.$quoted;
    }

    private function escapeUnquotedValue(string $value): string
    {
        return preg_replace('/([:\\(\\)!"&|])/', '\\\\$1', $value) ?? $value;
    }

    private function quoteValue(string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"'.$escaped.'"';
    }
}
