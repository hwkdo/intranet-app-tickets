<?php

declare(strict_types=1);

use App\Data\UserSettings;
use Hwkdo\IntranetAppTickets\Models\IntranetAppTicketsSettings;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, title};

title('Tickets - KI-Chat');

$appSettings = computed(function () {
    return IntranetAppTicketsSettings::current()?->settings;
});

$apiKey = computed(function () {
    $user = Auth::user();

    if (! $user) {
        return '';
    }

    $settings = UserSettings::from($user->settings);

    return (string) ($settings->ai->openWebUiApiToken ?? '');
});

$model = computed(function () {
    return (string) ($this->appSettings?->openWebUiModel ?? 'intranet-app-tickets');
});

$baseUrl = computed(function () {
    return (string) config('openwebui-api-laravel.base_api_url_ollama', 'https://chat.ai.hwk-do.com/api');
});

$hasApiKey = computed(fn (): bool => $this->apiKey !== '');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="KI-Chat" subheading="Support-Tickets mit KI und MCP-Server">
        @if ($this->hasApiKey)
            @livewire('prism-chat', [
                'appIdentifier' => 'tickets',
                'model' => $this->model,
                'apiKey' => $this->apiKey,
                'baseUrl' => $this->baseUrl,
                'useMcpTools' => true,
            ])
        @else
            <flux:card class="glass-card">
                <flux:callout variant="warning" class="mb-4">
                    <flux:heading size="sm">API-Token fehlt</flux:heading>
                    <flux:text>
                        Um den KI-Chat zu nutzen, müssen Sie einen OpenWebUI API-Token in Ihren globalen Einstellungen konfigurieren.
                    </flux:text>
                </flux:callout>

                <flux:button
                    variant="primary"
                    href="{{ route('settings.all') }}"
                >
                    Zu den Einstellungen
                </flux:button>
            </flux:card>
        @endif
    </x-intranet-app-tickets::tickets-layout>
</div>
