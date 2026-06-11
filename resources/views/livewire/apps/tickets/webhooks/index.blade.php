<?php

declare(strict_types=1);

use Spatie\WebhookClient\Models\WebhookCall;
use function Livewire\Volt\{computed, on, state, title, usesPagination};

usesPagination();

state([
    'filter' => '',
]);

$webhooks = computed(function () {
    return WebhookCall::query()
        ->where('name', 'tickets-zammad')
        ->with('zammadOutcome')
        ->when($this->filter !== '', function ($query) {
            $query->where(function ($inner) {
                $inner->where('url', 'like', '%'.$this->filter.'%')
                    ->orWhere('payload', 'like', '%'.$this->filter.'%');
            });
        })
        ->orderByDesc('created_at')
        ->paginate(15);
});

on(['echo-private:tickets-zammad-webhooks,.zammad.webhook.activity' => function (array $event) {
    unset($this->webhooks);
}]);

title('Tickets - Webhooks');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Zammad Webhooks" subheading="Eingegangene Webhook-Aufrufe">
        <flux:card class="glass-card">
            <div class="space-y-6">
                <flux:input
                    wire:model.live.debounce.300ms="filter"
                    placeholder="Suche in URL oder Payload..."
                    icon="magnifying-glass"
                    class="w-full max-w-md"
                />

                @if ($this->webhooks->isEmpty())
                    <flux:callout variant="secondary" icon="bell-slash">
                        Noch keine Zammad-Webhooks gespeichert. Prüfe den Trigger in Zammad und ob Horizon/Queue läuft.
                    </flux:callout>
                @else
                    <flux:table :paginate="$this->webhooks">
                        <flux:table.columns>
                            <flux:table.column>ID</flux:table.column>
                            <flux:table.column>Ticket</flux:table.column>
                            <flux:table.column>Sender</flux:table.column>
                            <flux:table.column>Verarbeitung</flux:table.column>
                            <flux:table.column>Empfangen</flux:table.column>
                            <flux:table.column>Aktionen</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->webhooks as $webhook)
                                @php
                                    $ticketNumber = $webhook->payload['ticket']['number'] ?? $webhook->payload['ticket']['id'] ?? '-';
                                    $article = $webhook->payload['article'] ?? null;
                                    $hasArticle = is_array($article) && $article !== [] && array_key_exists('id', $article);
                                    $sender = $hasArticle
                                        ? ($article['sender'] ?? '-')
                                        : ($webhook->payload['ticket']['state'] ?? 'Status');
                                    $outcome = $webhook->zammadOutcome;
                                @endphp
                                <flux:table.row wire:key="webhook-{{ $webhook->id }}">
                                    <flux:table.cell>{{ $webhook->id }}</flux:table.cell>
                                    <flux:table.cell>#{{ $ticketNumber }}</flux:table.cell>
                                    <flux:table.cell>{{ $sender }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($webhook->exception)
                                            <flux:badge color="red" size="sm">Fehler</flux:badge>
                                            <flux:text class="mt-1 block max-w-xs truncate text-xs text-zinc-500" title="{{ is_array($webhook->exception) ? json_encode($webhook->exception) : $webhook->exception }}">
                                                Job-Exception gespeichert
                                            </flux:text>
                                        @elseif ($outcome)
                                            <flux:badge :color="$outcome->status->badgeColor()" size="sm">
                                                {{ $outcome->status->label() }}
                                            </flux:badge>
                                            @if ($outcome->message)
                                                <flux:text class="mt-1 block max-w-xs truncate text-xs text-zinc-500" title="{{ $outcome->message }}">
                                                    {{ $outcome->message }}
                                                </flux:text>
                                            @endif
                                            @if ($outcome->processed_at)
                                                <flux:text class="mt-0.5 block text-xs text-zinc-400">
                                                    {{ $outcome->processed_at->format('d.m.Y H:i:s') }}
                                                </flux:text>
                                            @endif
                                        @else
                                            <flux:badge color="amber" size="sm">Wartet auf Verarbeitung</flux:badge>
                                            <flux:text class="mt-1 block text-xs text-zinc-500">
                                                Job noch nicht abgeschlossen oder Queue steht still
                                            </flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $webhook->created_at?->format('d.m.Y H:i:s') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button
                                            :href="route('apps.tickets.webhooks.show-payload', $webhook->id)"
                                            size="xs"
                                            icon="code-bracket"
                                            wire:navigate
                                        >
                                            Payload
                                        </flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </flux:card>
    </x-intranet-app-tickets::tickets-layout>
</div>
