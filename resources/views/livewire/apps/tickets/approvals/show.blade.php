<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TicketApprovalService;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{mount, state, title};

state([
    'ticketRequest' => null,
    'approvalNote' => '',
    'rejectionReason' => '',
    'showRejectForm' => false,
]);

mount(function (TicketRequest $ticketRequest): void {
    $this->authorize('approve', $ticketRequest);
    $this->ticketRequest = $ticketRequest->load(['category', 'requester', 'onBehalfOf', 'attachments']);
});

$approve = function (): void {
    $this->authorize('approve', $this->ticketRequest);

    $this->validate([
        'approvalNote' => ['nullable', 'string', 'max:1000'],
    ]);

    try {
        app(TicketApprovalService::class)->approve(
            $this->ticketRequest,
            Auth::user(),
            $this->approvalNote !== '' ? $this->approvalNote : null,
        );
    } catch (Throwable $e) {
        Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'danger');

        return;
    }

    Flux::toast(text: 'Anfrage genehmigt und übermittelt.', variant: 'success');
    $this->redirect(route('apps.tickets.approvals.index'), navigate: true);
};

$reject = function (): void {
    $this->authorize('reject', $this->ticketRequest);

    $this->validate([
        'rejectionReason' => ['required', 'string', 'max:1000'],
    ]);

    app(TicketApprovalService::class)->reject(
        $this->ticketRequest,
        Auth::user(),
        $this->rejectionReason,
    );

    Flux::toast(text: 'Anfrage abgelehnt.', variant: 'success');
    $this->redirect(route('apps.tickets.approvals.index'), navigate: true);
};

title(fn () => 'Genehmigung: '.($this->ticketRequest?->subject ?? ''));

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Genehmigung" :subheading="$ticketRequest->subject">
        <div class="mx-auto max-w-4xl space-y-6">
            <flux:card>
                <div class="space-y-3">
                    <flux:badge>{{ $ticketRequest->category->label }}</flux:badge>
                    <flux:text><strong>Antragsteller:</strong> {{ $ticketRequest->requester?->name }}</flux:text>
                    @if ($ticketRequest->onBehalfOf)
                        <flux:text><strong>Ticket für:</strong> {{ $ticketRequest->onBehalfOf->name }}</flux:text>
                    @endif
                    <flux:text><strong>Eingereicht:</strong> {{ $ticketRequest->created_at?->format('d.m.Y H:i') }}</flux:text>
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

            @unless ($showRejectForm)
                <flux:textarea wire:model="approvalNote" label="Genehmigungsnotiz (optional)" rows="3" />
                <div class="flex gap-3">
                    <flux:button wire:click="approve" variant="primary" icon="check">Genehmigen</flux:button>
                    <flux:button wire:click="$set('showRejectForm', true)" variant="danger" icon="x-mark">Ablehnen</flux:button>
                </div>
            @else
                <flux:textarea wire:model="rejectionReason" label="Ablehnungsgrund" rows="3" required />
                <div class="flex gap-3">
                    <flux:button wire:click="reject" variant="danger">Ablehnung bestätigen</flux:button>
                    <flux:button wire:click="$set('showRejectForm', false)" variant="ghost">Abbrechen</flux:button>
                </div>
            @endunless
        </div>
    </x-intranet-app-tickets::tickets-layout>
</div>
