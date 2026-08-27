<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Data\TicketListItem;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Enums\TicketListItemType;
use Hwkdo\IntranetAppTickets\Services\TicketListService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Hwkdo\IntranetAppTickets\Support\TicketTourDemo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, on, state, title, updated};

state([
    'userId' => null,
    'filter' => TicketFilterEnum::Open->value,
    'search' => '',
    'zammadMapped' => true,
    'tourDemo' => false,
    'tourDemoHoldLoading' => false,
    /** @var list<array<string, mixed>> */
    'tickets' => [],
    'zammadPage' => 1,
    'hasMore' => true,
    'loadingMore' => false,
]);

$displayTickets = computed(function (): Collection {
    return collect($this->tickets)
        ->map(fn (array $ticket): TicketListItem => TicketListItem::fromArray($ticket));
});

$hasAnyTickets = computed(fn (): bool => $this->displayTickets->isNotEmpty());

$resetTicketList = function (): void {
    $this->tickets = [];
    $this->zammadPage = 1;
    $this->hasMore = true;
    $this->loadingMore = false;
    $this->tourDemoHoldLoading = false;
};

$hasActiveSearch = computed(fn (): bool => trim($this->search) !== '');

$loadMoreTickets = function (): void {
    if ($this->tourDemoHoldLoading || $this->loadingMore || ! $this->hasMore) {
        return;
    }

    $this->loadingMore = true;

    try {
        $existing = collect($this->tickets)
            ->map(fn (array $ticket): TicketListItem => TicketListItem::fromArray($ticket));

        if ($this->tourDemo || TicketTourDemo::isActive()) {
            $this->tourDemo = true;
            $result = TicketTourDemo::fetchNextBatch(
                $this->zammadPage,
                $existing,
                TicketFilterEnum::from($this->filter),
            );
        } else {
            $search = trim($this->search);

            $result = app(TicketListService::class)->fetchNextBatch(
                user: Auth::user(),
                filter: TicketFilterEnum::from($this->filter),
                zammadPage: $this->zammadPage,
                includeRequests: $this->zammadPage === 1,
                existingItems: $existing,
                search: $search === '' ? null : $search,
            );
        }

        $this->tickets = [
            ...$this->tickets,
            ...array_map(
                fn (TicketListItem $item): array => $item->toArray(),
                $result->newItems,
            ),
        ];
        $this->hasMore = $result->hasMore;
        $this->zammadPage++;
    } finally {
        $this->loadingMore = false;
    }
};

$reloadTicketList = function (): void {
    $this->resetTicketList();
    $this->loadMoreTickets();
};

$clearSearch = function (): void {
    $this->search = '';
    $this->reloadTicketList();
};

$enableTourDemo = function (): void {
    TicketTourDemo::enable();
    $this->tourDemo = true;
    $this->reloadTicketList();
};

$refreshTourDemoList = function (): void {
    if (! TicketTourDemo::isActive()) {
        return;
    }

    $this->tourDemo = true;
    $this->reloadTicketList();
};

$disableTourDemo = function (): void {
    TicketTourDemo::disable();
    $this->tourDemo = false;
    $this->tourDemoHoldLoading = false;
    $this->reloadTicketList();
};

$beginTourInfiniteScrollDemo = function (): void {
    if (! $this->tourDemo && ! TicketTourDemo::isActive()) {
        return;
    }

    $this->tourDemo = true;
    $this->tourDemoHoldLoading = true;
};

$endTourInfiniteScrollDemo = function (): void {
    if (! $this->tourDemoHoldLoading) {
        return;
    }

    $this->tourDemoHoldLoading = false;
    $this->loadMoreTickets();
};

mount(function (ZammadUserResolver $userResolver) {
    $this->userId = Auth::id();
    $this->zammadMapped = $userResolver->resolveCustomerId(Auth::user()) !== null;
    $this->tourDemo = TicketTourDemo::isActive();
    $this->reloadTicketList();
});

updated([
    'filter' => function (): void {
        $this->reloadTicketList();
    },
    'search' => function (): void {
        if ($this->tourDemo) {
            return;
        }

        $this->reloadTicketList();
    },
]);

on([
    'echo-private:App.Models.User.{userId},.ticket.updated' => function (array $event) {
        Flux::toast(
            heading: 'Ticket-Update',
            text: 'Neues Update zu Ticket #'.($event['ticket_number'] ?? ''),
            variant: 'info',
        );
        $this->reloadTicketList();
    },
    'tickets-tour-demo-enable' => function () {
        $this->enableTourDemo();
    },
    'tickets-tour-demo-refresh' => function () {
        $this->refreshTourDemoList();
    },
    'tickets-tour-demo-disable' => function () {
        $this->disableTourDemo();
    },
]);

title('Tickets - Übersicht');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Meine Tickets" subheading="Tickets einsehen und verwalten">
        @if ($tourDemo)
            <flux:callout variant="secondary" icon="map" class="mb-4" data-tour="tickets-demo-banner">
                <flux:callout.heading>Tour-Demo aktiv</flux:callout.heading>
                <flux:callout.text>
                    Die angezeigten Tickets sind Beispieldaten für die Produkt-Tour und werden nicht gespeichert.
                </flux:callout.text>
                <x-slot:actions>
                    <flux:button wire:click="disableTourDemo" size="sm" variant="ghost">
                        Demo beenden
                    </flux:button>
                </x-slot:actions>
            </flux:callout>
        @endif

        @unless ($zammadMapped)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                Für Ihr Benutzerkonto wurde kein Zammad-Kunde gefunden. Sie können dennoch Ticketanfragen erstellen und deren Status verfolgen.
            </flux:callout>
        @endunless

        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center" data-tour="tickets-search">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Ticketnummer, Betreff, Inhalt …"
                    icon="magnifying-glass"
                    class="w-full sm:max-w-md"
                />

                @if ($this->hasActiveSearch)
                    <flux:button
                        wire:click="clearSearch"
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                    >
                        Suche zurücksetzen
                    </flux:button>
                @endif
            </div>

            <flux:tab.group>
                <div class="flex flex-wrap items-center gap-3" data-tour="tickets-tabs">
                    <flux:tabs wire:model.live="filter" class="min-w-0 flex-1">
                        <flux:tab name="open">Offen</flux:tab>
                        <flux:tab name="closed">Geschlossen</flux:tab>
                        <flux:tab name="all">Alle</flux:tab>
                    </flux:tabs>

                    <div
                        wire:loading
                        wire:target="filter, search, loadMoreTickets"
                        class="flex shrink-0 items-center gap-2 text-sm text-zinc-500"
                    >
                        <flux:icon.loading variant="micro" />
                        <span>Tickets werden geladen…</span>
                    </div>
                </div>
            </flux:tab.group>

            <div wire:loading wire:target="filter, search" class="space-y-3">
                @foreach (range(1, 4) as $placeholder)
                    <flux:card wire:key="ticket-skeleton-{{ $placeholder }}" class="glass-card">
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <flux:skeleton class="h-5 w-24" />
                                <flux:skeleton class="h-5 w-16 rounded-full" />
                            </div>
                            <flux:skeleton class="h-5 w-3/4" />
                            <flux:skeleton class="h-4 w-40" />
                        </div>
                    </flux:card>
                @endforeach
            </div>

            <div wire:loading.remove wire:target="filter, search" data-tour="tickets-list">
                @unless ($this->hasAnyTickets)
                    <flux:callout variant="secondary" icon="ticket">
                        @if ($this->hasActiveSearch)
                            Keine Tickets für diese Suche gefunden.
                        @else
                            Keine Tickets in dieser Ansicht gefunden.
                        @endif
                    </flux:callout>
                @else
                    <div class="space-y-3">
                        @foreach ($this->displayTickets as $ticket)
                            <a
                                wire:key="ticket-{{ $ticket->type->value }}-{{ $ticket->id }}"
                                href="{{ $ticket->url }}"
                                wire:navigate
                                class="block"
                                @if ($loop->first) data-tour="tickets-first-card" @endif
                            >
                                <flux:card @class([
                                    'glass-card cursor-pointer transition hover:border-zinc-400/50',
                                    'ring-2 ring-amber-400/60' => $ticket->isUnread,
                                ])>
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1 space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <flux:heading size="sm">#{{ $ticket->number ?? $ticket->id }}</flux:heading>
                                                @if ($ticket->badge)
                                                    <span @if ($ticket->badge === 'Neu') data-tour="tickets-unread-badge" @endif>
                                                        <flux:badge
                                                            size="sm"
                                                            :color="match ($ticket->badge) {
                                                                'Neu' => 'amber',
                                                                'Zur Genehmigung' => 'amber',
                                                                'Abgelehnt' => 'red',
                                                                'Fehlgeschlagen' => 'red',
                                                                default => 'zinc',
                                                            }"
                                                        >
                                                            {{ $ticket->badge }}
                                                        </flux:badge>
                                                    </span>
                                                @endif
                                                <flux:badge size="sm">{{ $ticket->statusLabel }}</flux:badge>
                                                @if ($ticket->type === TicketListItemType::Request)
                                                    <flux:badge size="sm" color="zinc">Anfrage</flux:badge>
                                                @endif
                                            </div>
                                            <flux:text class="font-medium">{{ $ticket->title }}</flux:text>
                                            <flux:text class="text-sm text-zinc-500">
                                                Aktualisiert: {{ $ticket->updatedAt?->diffForHumans() ?? '—' }}
                                            </flux:text>
                                        </div>
                                        <flux:icon.chevron-right class="size-5 shrink-0 text-zinc-400" />
                                    </div>
                                </flux:card>
                            </a>
                        @endforeach
                    </div>

                    @if ($hasMore || $tourDemoHoldLoading)
                        <div
                            @if (! $tourDemoHoldLoading)
                                wire:intersect="loadMoreTickets"
                            @endif
                            wire:key="ticket-list-sentinel"
                            @class([
                                'flex flex-col items-center justify-center gap-2',
                                'border border-dashed border-zinc-300 py-10 dark:border-zinc-600' => $tourDemo,
                                'py-6' => ! $tourDemo,
                            ])
                            data-tour="tickets-infinite-scroll"
                        >
                            @if ($tourDemo)
                                <flux:icon.arrow-down class="size-5 text-zinc-400" />
                            @endif
                            @if ($tourDemoHoldLoading)
                                <div class="flex items-center gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                    <flux:icon.loading variant="micro" />
                                    <span>Weitere Tickets laden…</span>
                                </div>
                            @else
                                <div
                                    wire:loading
                                    wire:target="loadMoreTickets"
                                    @class([
                                        'flex items-center gap-2 text-sm',
                                        'font-medium text-zinc-700 dark:text-zinc-200' => $tourDemo,
                                        'text-zinc-500' => ! $tourDemo,
                                    ])
                                >
                                    <flux:icon.loading variant="micro" />
                                    <span>Weitere Tickets laden…</span>
                                </div>
                                <flux:text wire:loading.remove wire:target="loadMoreTickets" class="text-sm text-zinc-500">
                                    Weiter scrollen für mehr Tickets…
                                </flux:text>
                            @endif
                        </div>
                    @endif
                @endunless
            </div>
        </div>
    </x-intranet-app-tickets::tickets-layout>
</div>
