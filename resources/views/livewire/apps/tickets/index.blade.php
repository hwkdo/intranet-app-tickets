<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Data\TicketListItem;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Enums\TicketListItemType;
use Hwkdo\IntranetAppTickets\Services\TicketListService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, on, state, title, updated};

state([
    'filter' => TicketFilterEnum::Open->value,
    'search' => '',
    'zammadMapped' => true,
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
};

$hasActiveSearch = computed(fn (): bool => trim($this->search) !== '');

$loadMoreTickets = function (): void {
    if ($this->loadingMore || ! $this->hasMore) {
        return;
    }

    $this->loadingMore = true;

    try {
        $existing = collect($this->tickets)
            ->map(fn (array $ticket): TicketListItem => TicketListItem::fromArray($ticket));

        $search = trim($this->search);

        $result = app(TicketListService::class)->fetchNextBatch(
            user: Auth::user(),
            filter: TicketFilterEnum::from($this->filter),
            zammadPage: $this->zammadPage,
            includeRequests: $this->zammadPage === 1,
            existingItems: $existing,
            search: $search === '' ? null : $search,
        );

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

mount(function (ZammadUserResolver $userResolver) {
    $this->zammadMapped = $userResolver->resolveCustomerId(Auth::user()) !== null;
    $this->reloadTicketList();
});

updated([
    'filter' => function (): void {
        $this->reloadTicketList();
    },
    'search' => function (): void {
        $this->reloadTicketList();
    },
]);

on(['echo-private:App.Models.User.'.auth()->id().',.ticket.updated' => function (array $event) {
    Flux::toast(
        heading: 'Ticket-Update',
        text: 'Neues Update zu Ticket #'.($event['ticket_number'] ?? ''),
        variant: 'info',
    );
    $this->reloadTicketList();
}]);

title('Tickets - Übersicht');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Meine Tickets" subheading="Tickets einsehen und verwalten">
        @unless ($zammadMapped)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                Für Ihr Benutzerkonto wurde kein Zammad-Kunde gefunden. Sie können dennoch Ticketanfragen erstellen und deren Status verfolgen.
            </flux:callout>
        @endunless

        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
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
                <div class="flex flex-wrap items-center gap-3">
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

            <div wire:loading.remove wire:target="filter, search">
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
                            >
                                <flux:card class="glass-card cursor-pointer transition hover:border-zinc-400/50">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1 space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <flux:heading size="sm">#{{ $ticket->number ?? $ticket->id }}</flux:heading>
                                                @if ($ticket->badge)
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

                    @if ($hasMore)
                        <div
                            wire:intersect="loadMoreTickets"
                            wire:key="ticket-list-sentinel"
                            class="flex justify-center py-6"
                        >
                            <div
                                wire:loading
                                wire:target="loadMoreTickets"
                                class="flex items-center gap-2 text-sm text-zinc-500"
                            >
                                <flux:icon.loading variant="micro" />
                                <span>Weitere Tickets laden…</span>
                            </div>
                        </div>
                    @endif
                @endunless
            </div>
        </div>
    </x-intranet-app-tickets::tickets-layout>
</div>
