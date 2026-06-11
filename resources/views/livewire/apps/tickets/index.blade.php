<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Enums\TicketListItemType;
use Hwkdo\IntranetAppTickets\Services\TicketListService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, on, state, title};

state([
    'filter' => TicketFilterEnum::Open->value,
    'zammadMapped' => true,
]);

mount(function (ZammadUserResolver $userResolver) {
    $this->zammadMapped = $userResolver->resolveCustomerId(Auth::user()) !== null;
});

$tickets = computed(fn () => app(TicketListService::class)->listForUser(
    Auth::user(),
    TicketFilterEnum::from($this->filter),
));

$hasAnyTickets = computed(fn () => $this->tickets->isNotEmpty());

on(['echo-private:App.Models.User.'.auth()->id().',.ticket.updated' => function (array $event) {
    Flux::toast(
        heading: 'Ticket-Update',
        text: 'Neues Update zu Ticket #'.($event['ticket_number'] ?? ''),
        variant: 'info',
    );
    unset($this->tickets);
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
            <flux:tab.group>
                <flux:tabs wire:model.live="filter">
                    <flux:tab name="open">Offen</flux:tab>
                    <flux:tab name="closed">Geschlossen</flux:tab>
                    <flux:tab name="all">Alle</flux:tab>
                </flux:tabs>
            </flux:tab.group>

            @unless ($this->hasAnyTickets)
                <flux:callout variant="secondary" icon="ticket">
                    Keine Tickets in dieser Ansicht gefunden.
                </flux:callout>
            @else
                <div class="space-y-3">
                    @foreach ($this->tickets as $ticket)
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
            @endunless
        </div>
    </x-intranet-app-tickets::tickets-layout>
</div>
