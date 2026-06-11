<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use function Livewire\Volt\{mount, state, title};

state(['ticketRequest' => null]);

mount(function (TicketRequest $ticketRequest): void {
    $this->authorize('view', $ticketRequest);
    $this->ticketRequest = $ticketRequest->load(['category', 'requester', 'onBehalfOf', 'approver', 'attachments']);
});

title(fn () => 'Anfrage: '.($this->ticketRequest?->subject ?? ''));

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Ticketanfrage" :subheading="$ticketRequest->subject">
        <div class="mx-auto max-w-4xl space-y-6">
            <flux:card>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge>#A-{{ $ticketRequest->id }}</flux:badge>
                    <flux:badge color="{{ $ticketRequest->status->value === 'pending' ? 'amber' : ($ticketRequest->status->value === 'rejected' ? 'red' : 'zinc') }}">
                        {{ $ticketRequest->status->label() }}
                    </flux:badge>
                    <flux:badge>{{ $ticketRequest->category->label }}</flux:badge>
                </div>
                <div class="mt-4 space-y-2">
                    <flux:text><strong>Kategorie:</strong> {{ $ticketRequest->category->label }}</flux:text>
                    <flux:text><strong>Erstellt:</strong> {{ $ticketRequest->created_at?->format('d.m.Y H:i') }}</flux:text>
                    @if ($ticketRequest->dispatched_at)
                        <flux:text><strong>Übermittelt:</strong> {{ $ticketRequest->dispatched_at->format('d.m.Y H:i') }}</flux:text>
                    @endif
                    @if ($ticketRequest->zammad_ticket_id)
                        <flux:text>
                            <strong>Zammad-Ticket:</strong>
                            <a href="{{ route('apps.tickets.show', $ticketRequest->zammad_ticket_id) }}" wire:navigate class="text-blue-600 underline">
                                #{{ $ticketRequest->zammad_ticket_id }}
                            </a>
                        </flux:text>
                    @endif
                    @if ($ticketRequest->rejection_reason)
                        <flux:callout variant="danger" icon="x-circle">
                            {{ $ticketRequest->rejection_reason }}
                        </flux:callout>
                    @endif
                    @if ($ticketRequest->dispatch_error)
                        <flux:callout variant="danger" icon="exclamation-triangle">
                            {{ $ticketRequest->dispatch_error }}
                        </flux:callout>
                    @endif
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="sm" class="mb-3">Inhalt</flux:heading>
                <div class="prose prose-sm max-w-none whitespace-pre-wrap dark:prose-invert">{{ $ticketRequest->body }}</div>
            </flux:card>

            @if ($ticketRequest->attachments->isNotEmpty())
                <flux:card>
                    <flux:heading size="sm" class="mb-3">Anhänge</flux:heading>
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($ticketRequest->attachments as $attachment)
                            <li>{{ $attachment->original_name }}</li>
                        @endforeach
                    </ul>
                </flux:card>
            @endif

            <flux:button href="{{ route('apps.tickets.index') }}" variant="ghost" wire:navigate>
                Zurück zur Übersicht
            </flux:button>
        </div>
    </x-intranet-app-tickets::tickets-layout>
</div>
