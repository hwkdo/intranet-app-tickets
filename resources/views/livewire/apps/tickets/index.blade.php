<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
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

$tickets = computed(function () {
    if (! $this->zammadMapped) {
        return collect();
    }

    return app(ZammadTicketService::class)->listTicketsForUser(
        Auth::user(),
        TicketFilterEnum::from($this->filter),
    );
});

$unreadTicketIds = computed(function () {
    return app(TicketReadStateService::class)->unreadForUser(Auth::user())
        ->pluck('zammad_ticket_id')
        ->all();
});

on(['echo-private:App.Models.User.'.auth()->id().',.ticket.updated' => function (array $event) {
    Flux::toast(
        heading: 'Ticket-Update',
        text: 'Neues Update zu Ticket #'.($event['ticket_number'] ?? ''),
        variant: 'info',
    );
    unset($this->tickets, $this->unreadTicketIds);
}]);

title('Tickets - Übersicht');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Meine Tickets" subheading="Zammad-Tickets einsehen und beantworten">
        @unless ($zammadMapped)
            <flux:callout variant="warning" icon="exclamation-triangle">
                Für Ihr Benutzerkonto wurde kein Zammad-Kunde gefunden. Bitte wenden Sie sich an den Support.
            </flux:callout>
        @else
            <div class="space-y-6">
                <flux:tab.group>
                    <flux:tabs wire:model.live="filter">
                        <flux:tab name="open">Offen</flux:tab>
                        <flux:tab name="closed">Geschlossen</flux:tab>
                        <flux:tab name="all">Alle</flux:tab>
                    </flux:tabs>
                </flux:tab.group>

                @if ($this->tickets->isEmpty())
                    <flux:callout variant="secondary" icon="ticket">
                        Keine Tickets in dieser Ansicht gefunden.
                    </flux:callout>
                @else
                    <div class="space-y-3">
                        @foreach ($this->tickets as $ticket)
                            <a
                                wire:key="ticket-{{ $ticket['id'] }}"
                                href="{{ route('apps.tickets.show', $ticket['id']) }}"
                                wire:navigate
                                class="block"
                            >
                            <flux:card class="glass-card cursor-pointer transition hover:border-zinc-400/50">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:heading size="sm">#{{ $ticket['number'] ?? $ticket['id'] }}</flux:heading>
                                            @if (in_array($ticket['id'], $this->unreadTicketIds))
                                                <flux:badge color="amber" size="sm">Neu</flux:badge>
                                            @endif
                                            <flux:badge size="sm">{{ $ticket['state'] ?? 'unbekannt' }}</flux:badge>
                                        </div>
                                        <flux:text class="font-medium">{{ $ticket['title'] ?? 'Ohne Titel' }}</flux:text>
                                        <flux:text class="text-sm text-zinc-500">
                                            Aktualisiert: {{ isset($ticket['updated_at']) ? \Illuminate\Support\Carbon::parse($ticket['updated_at'])->diffForHumans() : '—' }}
                                        </flux:text>
                                    </div>
                                    <flux:icon.chevron-right class="size-5 shrink-0 text-zinc-400" />
                                </div>
                            </flux:card>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endunless
    </x-intranet-app-tickets::tickets-layout>
</div>
