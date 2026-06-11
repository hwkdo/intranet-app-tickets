<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Services\TicketApprovalService;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, title};

mount(function (): void {
    if (! app(TicketApprovalService::class)->userCanApproveAny(Auth::user())) {
        abort(403);
    }
});

$requests = computed(fn () => app(TicketApprovalService::class)->pendingRequestsForUser(Auth::user()));

title('Ticket-Genehmigungen');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Genehmigungen" subheading="Offene Ticketanfragen">
        @if ($this->requests->isEmpty())
            <flux:callout variant="secondary" icon="check-badge">
                Keine offenen Genehmigungsanfragen.
            </flux:callout>
        @else
            <div class="space-y-3">
                @foreach ($this->requests as $request)
                    <a
                        wire:key="approval-{{ $request->id }}"
                        href="{{ route('apps.tickets.approvals.show', $request) }}"
                        wire:navigate
                        class="block"
                    >
                        <flux:card class="glass-card cursor-pointer transition hover:border-zinc-400/50">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:heading size="sm">#A-{{ $request->id }}</flux:heading>
                                        <flux:badge size="sm" color="amber">Zur Genehmigung</flux:badge>
                                    </div>
                                    <flux:text class="font-medium">{{ $request->subject }}</flux:text>
                                    <flux:text class="text-sm text-zinc-500">
                                        {{ $request->category->label }} · {{ $request->requester?->name }} · {{ $request->created_at?->diffForHumans() }}
                                    </flux:text>
                                </div>
                                <flux:icon.chevron-right class="size-5 shrink-0 text-zinc-400" />
                            </div>
                        </flux:card>
                    </a>
                @endforeach
            </div>
        @endif
    </x-intranet-app-tickets::tickets-layout>
</div>
