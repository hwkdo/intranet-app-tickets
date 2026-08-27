<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Hwkdo\IntranetAppTickets\Data\AppSettings;
use Hwkdo\IntranetAppTickets\Enums\TeamsTicketAiProvider;
use Hwkdo\IntranetAppTickets\Services\TicketsAppSettingsStore;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{computed, mount, state};

state([
    'aiTextProviderOverride' => '',
    'aiTextModelOverride' => '',
    'aiImageProviderOverride' => '',
    'aiImageModelOverride' => '',
    'teamsTicketAiEnabled' => true,
    'teamsTicketAiProvider' => 'langdock',
    'teamsTicketAiModelOpenWebUi' => '',
    'teamsTicketAiModelLangdock' => '',
    'openWebUiModel' => 'intranet-app-tickets',
]);

mount(function (): void {
    $settings = app(TicketsAppSettingsStore::class)->current();

    $this->aiTextProviderOverride = $settings->aiTextProviderOverride?->value ?? '';
    $this->aiTextModelOverride = $settings->textModelOverride() ?? '';
    $this->aiImageProviderOverride = $settings->aiImageProviderOverride?->value ?? '';
    $this->aiImageModelOverride = $settings->imageModelOverride() ?? '';
    $this->teamsTicketAiEnabled = $settings->teamsTicketAiEnabled;
    $this->teamsTicketAiProvider = $settings->teamsTicketAiProvider->value;
    $this->teamsTicketAiModelOpenWebUi = $settings->teamsTicketAiModelOpenWebUi;
    $this->teamsTicketAiModelLangdock = $settings->teamsTicketAiModelLangdock;
    $this->openWebUiModel = $settings->openWebUiModel;
});

$baseAiTextSummary = computed(function (): string {
    $base = app(IntranetBaseAiConfigSourceInterface::class);
    $model = $base->textModel() ?? 'Provider-Standard';

    return $base->textProvider()->label().' / '.$model;
});

$baseAiImageSummary = computed(function (): string {
    $base = app(IntranetBaseAiConfigSourceInterface::class);
    $model = $base->imageModel() ?? 'Provider-Standard';

    return $base->imageProvider()->label().' / '.$model;
});

$effectiveAiTextSummary = computed(function (): string {
    $resolved = app(AiConfigResolverInterface::class)->resolve('tickets', AiCapability::Text);

    return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
});

$effectiveAiImageSummary = computed(function (): string {
    $resolved = app(AiConfigResolverInterface::class)->resolve('tickets', AiCapability::Image);

    return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
});

$save = function (): void {
    $this->validate([
        'aiTextProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
        'aiTextModelOverride' => 'nullable|string|max:100',
        'aiImageProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
        'aiImageModelOverride' => 'nullable|string|max:100',
        'teamsTicketAiEnabled' => 'boolean',
        'teamsTicketAiProvider' => ['required', 'string', Rule::enum(TeamsTicketAiProvider::class)],
        'teamsTicketAiModelOpenWebUi' => 'nullable|string|max:255',
        'teamsTicketAiModelLangdock' => 'nullable|string|max:255',
        'openWebUiModel' => 'required|string|max:255',
    ]);

    $parseProviderOverride = function (string $value): ?AiProvider {
        $trimmed = trim($value);

        return $trimmed === '' ? null : AiProvider::from($trimmed);
    };

    $blankToNull = function (?string $value): ?string {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    };

    $current = app(TicketsAppSettingsStore::class)->current();

    $settings = AppSettings::from(array_merge($current->toArray(), [
        'aiTextProviderOverride' => $parseProviderOverride($this->aiTextProviderOverride),
        'aiTextModelOverride' => $blankToNull($this->aiTextModelOverride),
        'aiImageProviderOverride' => $parseProviderOverride($this->aiImageProviderOverride),
        'aiImageModelOverride' => $blankToNull($this->aiImageModelOverride),
        'teamsTicketAiEnabled' => $this->teamsTicketAiEnabled,
        'teamsTicketAiProvider' => TeamsTicketAiProvider::from($this->teamsTicketAiProvider),
        'teamsTicketAiModelOpenWebUi' => trim($this->teamsTicketAiModelOpenWebUi),
        'teamsTicketAiModelLangdock' => trim($this->teamsTicketAiModelLangdock),
        'openWebUiModel' => trim($this->openWebUiModel),
    ]));

    app(TicketsAppSettingsStore::class)->save($settings);

    unset($this->baseAiTextSummary, $this->baseAiImageSummary, $this->effectiveAiTextSummary, $this->effectiveAiImageSummary);

    Flux::toast(
        heading: 'Gespeichert',
        text: 'KI-Einstellungen wurden gespeichert.',
        variant: 'success',
    );
};

?>

<flux:card class="glass-card">
    <flux:heading size="lg" class="mb-2">KI-Einstellungen</flux:heading>
    <flux:text class="mb-6 text-sm text-zinc-500">
        Gateway-Overrides, Teams-Bot-Ticketformulierung und Tickets-KI-Chat. Leere Override-Felder nutzen die globalen Einstellungen unter Manager → Base Settings.
    </flux:text>

    <div class="space-y-8">
        <div>
            <flux:heading size="sm" class="mb-3">Gateway (Text & Bild)</flux:heading>

            <flux:callout class="mb-4" icon="information-circle">
                <flux:callout.heading>Globale KI-Standards</flux:callout.heading>
                <flux:callout.text>
                    Text: <strong>{{ $this->baseAiTextSummary }}</strong><br>
                    Bilder: <strong>{{ $this->baseAiImageSummary }}</strong>
                    — änderbar unter Manager → Base Settings.
                </flux:callout.text>
            </flux:callout>

            <x-intranet-app-base::admin-ai-settings
                ai-text-provider-override="aiTextProviderOverride"
                ai-text-model-override="aiTextModelOverride"
                ai-image-provider-override="aiImageProviderOverride"
                ai-image-model-override="aiImageModelOverride"
            />

            <flux:text class="mt-4 text-sm text-zinc-500">
                Aktuell wirksam für Tickets:
                Text <strong>{{ $this->effectiveAiTextSummary }}</strong>,
                Bilder <strong>{{ $this->effectiveAiImageSummary }}</strong>.
            </flux:text>
        </div>

        <flux:separator />

        <div>
            <flux:heading size="sm" class="mb-3">Teams-Bot Ticketformulierung</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-500">
                Steuert die KI-gestützte Betreff- und Inhaltsformulierung bei Ticket-Erstellung über Microsoft Teams.
            </flux:text>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>KI für Teams-Bot-Tickets aktiv</flux:label>
                    <flux:switch wire:model="teamsTicketAiEnabled" />
                </flux:field>

                <flux:select wire:model.live="teamsTicketAiProvider" label="KI-Backend">
                    @foreach (\Hwkdo\IntranetAppTickets\Enums\TeamsTicketAiProvider::options() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($teamsTicketAiProvider === 'openwebui')
                    <flux:input
                        wire:model="teamsTicketAiModelOpenWebUi"
                        class="md:col-span-2"
                        label="Modell (Open Web UI)"
                        placeholder="z. B. gpt-oss:20b"
                    />
                @else
                    <flux:input
                        wire:model="teamsTicketAiModelLangdock"
                        class="md:col-span-2"
                        label="Modell (Langdock)"
                        placeholder="z. B. gpt-4o"
                    />
                @endif
            </div>
        </div>

        <flux:separator />

        <div>
            <flux:heading size="sm" class="mb-3">Tickets-KI-Chat</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-500">
                Interaktiver Chat (prism-chat / Open Web UI) mit MCP-Server-Unterstützung für Ticket-Übersicht, Details und Erstellung.
                Der System-Prompt für den OpenWebUI-Agenten wird dort konfiguriert; der MCP-Server ist unter <code>/mcp/apps/tickets</code> erreichbar.
            </flux:text>

            <flux:input
                wire:model="openWebUiModel"
                label="Open Web UI Modell"
                description="Modellname in Open Web UI für den Tickets-Assistenten (z. B. intranet-app-tickets)."
            />
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <flux:button wire:click="save" variant="primary">
            KI-Einstellungen speichern
        </flux:button>
    </div>
</flux:card>
