<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Support;

use Carbon\Carbon;
use Hwkdo\IntranetAppTickets\Data\TicketListItem;
use Hwkdo\IntranetAppTickets\Data\TicketListPageResult;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Enums\TicketListItemType;
use Illuminate\Support\Collection;

class TicketTourDemo
{
    public const SESSION_KEY = 'intranet_tickets_tour_demo';

    public const DEMO_TICKET_ID_START = 990001;

    public const DEMO_TICKET_COUNT = 24;

    public const PAGE_SIZE = 8;

    public static function isActive(): bool
    {
        return (bool) session(self::SESSION_KEY, false);
    }

    public static function enable(): void
    {
        session([self::SESSION_KEY => true]);
    }

    public static function disable(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->forget(self::SESSION_KEY.':updated');
    }

    public static function isDemoTicketId(int $ticketId): bool
    {
        return $ticketId >= self::DEMO_TICKET_ID_START
            && $ticketId < self::DEMO_TICKET_ID_START + self::DEMO_TICKET_COUNT;
    }

    public static function markFirstTicketUpdated(): void
    {
        session([self::SESSION_KEY.':updated' => true]);
    }

    public static function firstTicketWasUpdated(): bool
    {
        return (bool) session(self::SESSION_KEY.':updated', false);
    }

    /**
     * @return list<TicketListItem>
     */
    public static function allTickets(): array
    {
        $updated = self::firstTicketWasUpdated();
        $items = [];

        for ($i = 0; $i < self::DEMO_TICKET_COUNT; $i++) {
            $id = self::DEMO_TICKET_ID_START + $i;
            $isFirst = $i === 0;
            $isUnread = $isFirst && $updated;

            $items[] = new TicketListItem(
                type: TicketListItemType::Zammad,
                id: $id,
                number: (string) (100000 + $i),
                title: match ($i) {
                    0 => 'VPN-Zugang funktioniert nicht',
                    1 => 'Neues Notebook beantragen',
                    2 => 'Drucker im 3. OG gestört',
                    default => 'Demo-Ticket '.($i + 1).' – Beispielanfrage',
                },
                statusLabel: ($i !== 0 && $i % 5 === 0) ? 'geschlossen' : 'offen',
                updatedAt: Carbon::now()->subMinutes(5 + ($i * 7)),
                url: route('apps.tickets.show', $id),
                isUnread: $isUnread,
                badge: $isUnread ? 'Neu' : null,
            );
        }

        return $items;
    }

    /**
     * @param  Collection<int, TicketListItem>  $existingItems
     */
    public static function fetchNextBatch(
        int $page,
        Collection $existingItems,
        TicketFilterEnum $filter = TicketFilterEnum::All,
    ): TicketListPageResult {
        $all = collect(self::allTickets());

        $all = match ($filter) {
            TicketFilterEnum::Open => $all
                ->filter(fn (TicketListItem $item): bool => $item->statusLabel !== 'geschlossen')
                ->values(),
            TicketFilterEnum::Closed => $all
                ->filter(fn (TicketListItem $item): bool => $item->statusLabel === 'geschlossen')
                ->values(),
            TicketFilterEnum::All => $all,
        };

        $offset = max(0, ($page - 1) * self::PAGE_SIZE);
        $slice = $all->slice($offset, self::PAGE_SIZE)->values();

        $existingKeys = $existingItems
            ->map(fn (TicketListItem $item): string => $item->type->value.'-'.$item->id)
            ->all();

        $newItems = $slice
            ->reject(fn (TicketListItem $item): bool => in_array($item->type->value.'-'.$item->id, $existingKeys, true))
            ->values()
            ->all();

        $hasMore = ($offset + self::PAGE_SIZE) < $all->count();

        return new TicketListPageResult(
            newItems: $newItems,
            hasMore: $hasMore,
        );
    }

    /**
     * @return array{id: int, number: string, title: string, state: string, owner_id: null}
     */
    public static function demoTicket(int $ticketId): array
    {
        $index = $ticketId - self::DEMO_TICKET_ID_START;

        return [
            'id' => $ticketId,
            'number' => (string) (100000 + $index),
            'title' => $index === 0
                ? 'VPN-Zugang funktioniert nicht'
                : 'Demo-Ticket '.($index + 1).' – Beispielanfrage',
            'state' => 'offen',
            'owner_id' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function demoArticles(int $ticketId): array
    {
        return [
            [
                'id' => 1,
                'sender' => 'Customer',
                'created_at' => Carbon::now()->subDays(2)->toIso8601String(),
                'body' => '<p>Hallo, ich komme seit heute Morgen nicht mehr ins VPN. Fehlermeldung: „Connection timed out“.</p>',
                'attachments' => [],
            ],
            [
                'id' => 2,
                'sender' => 'Agent',
                'created_at' => Carbon::now()->subDay()->toIso8601String(),
                'body' => '<p>Guten Tag, bitte prüfen Sie, ob der Client aktuell ist. Wir haben Ihren Zugang zurückgesetzt – bitte melden Sie sich erneut an.</p>',
                'attachments' => [
                    [
                        'id' => 1,
                        'filename' => 'vpn-anleitung.pdf',
                    ],
                ],
            ],
        ];
    }
}
