<?php

declare(strict_types=1);

use Spatie\WebhookClient\Models\WebhookCall;
use function Livewire\Volt\{mount, on, state, title};

state([
    'webhook' => null,
]);

mount(function (int $id) {
    $this->webhook = WebhookCall::query()
        ->where('name', 'tickets-zammad')
        ->with('zammadOutcome')
        ->findOrFail($id);
});

on(['echo-private:tickets-zammad-webhooks,.zammad.webhook.activity' => function (array $event) {
    if ((int) ($event['webhook_call_id'] ?? 0) !== (int) $this->webhook?->id) {
        return;
    }

    $this->webhook = WebhookCall::query()
        ->where('name', 'tickets-zammad')
        ->with('zammadOutcome')
        ->find($this->webhook->id);
}]);

title(fn () => 'Webhook #'.($this->webhook?->id ?? ''));

?>

<div>
    <x-intranet-app-tickets::tickets-layout
        heading="Webhook #{{ $webhook->id }}"
        subheading="Payload-Details"
    >
        <div class="space-y-6">
            <flux:card class="glass-card">
                <flux:heading size="sm" class="mb-3">Metadaten</flux:heading>
                <dl class="grid gap-2 text-sm md:grid-cols-2">
                    <div><dt class="text-zinc-500">URL</dt><dd class="font-mono break-all">{{ $webhook->url }}</dd></div>
                    <div><dt class="text-zinc-500">Empfangen</dt><dd>{{ $webhook->created_at?->format('d.m.Y H:i:s') }}</dd></div>
                    <div class="md:col-span-2">
                        <dt class="text-zinc-500">Verarbeitung</dt>
                        <dd class="mt-1">
                            @if ($webhook->exception)
                                <flux:badge color="red" size="sm">Fehler</flux:badge>
                            @elseif ($webhook->zammadOutcome)
                                <flux:badge :color="$webhook->zammadOutcome->status->badgeColor()" size="sm">
                                    {{ $webhook->zammadOutcome->status->label() }}
                                </flux:badge>
                                @if ($webhook->zammadOutcome->message)
                                    <p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ $webhook->zammadOutcome->message }}</p>
                                @endif
                                @if ($webhook->zammadOutcome->processed_at)
                                    <p class="mt-1 text-xs text-zinc-400">
                                        Verarbeitet am {{ $webhook->zammadOutcome->processed_at->format('d.m.Y H:i:s') }}
                                    </p>
                                @endif
                            @else
                                <flux:badge color="amber" size="sm">Wartet auf Verarbeitung</flux:badge>
                            @endif
                        </dd>
                    </div>
                    @if ($webhook->exception)
                        <div class="md:col-span-2">
                            <dt class="text-zinc-500">Exception</dt>
                            <dd class="font-mono text-red-600 whitespace-pre-wrap">{{ json_encode($webhook->exception, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</dd>
                        </div>
                    @endif
                </dl>
            </flux:card>

            <flux:card class="glass-card">
                <flux:heading size="sm" class="mb-3">Payload</flux:heading>
                <pre class="overflow-x-auto rounded-lg bg-zinc-950 p-4 text-xs text-zinc-100">{{ json_encode($webhook->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </flux:card>

            <flux:button variant="ghost" :href="route('apps.tickets.webhooks.index')" wire:navigate icon="arrow-left">
                Zurück zur Übersicht
            </flux:button>
        </div>
    </x-intranet-app-tickets::tickets-layout>
</div>
