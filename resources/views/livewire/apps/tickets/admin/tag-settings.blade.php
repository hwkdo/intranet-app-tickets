<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Models\TicketGvpTag;
use Hwkdo\IntranetAppTickets\Models\TicketStandortTag;
use function Livewire\Volt\{computed, mount, state};

state([
    'activeSection' => 'standort',
    'standortTags' => [],
    'gvpTags' => [],
]);

mount(function (): void {
    $standortModel = config('intranet-app-tickets.standort_model');
    $gvpModel = config('intranet-app-tickets.gvp_model');

    $existingStandortTags = TicketStandortTag::query()->pluck('tag', 'standort_id');
    $existingGvpTags = TicketGvpTag::query()->pluck('tag', 'gvp_id');

    $this->standortTags = $standortModel::query()
        ->orderBy('name')
        ->get(['id'])
        ->mapWithKeys(fn (object $standort): array => [
            $standort->id => (string) ($existingStandortTags[$standort->id] ?? ''),
        ])
        ->all();

    $this->gvpTags = $gvpModel::query()
        ->orderBy('kuerzel')
        ->orderBy('nummer')
        ->orderBy('name')
        ->get(['id'])
        ->mapWithKeys(fn (object $gvp): array => [
            $gvp->id => (string) ($existingGvpTags[$gvp->id] ?? ''),
        ])
        ->all();
});

$standorte = computed(function () {
    $model = config('intranet-app-tickets.standort_model');

    return $model::query()->orderBy('name')->get(['id', 'name']);
});

$gvps = computed(function () {
    $model = config('intranet-app-tickets.gvp_model');

    return $model::query()->orderBy('kuerzel')->orderBy('nummer')->orderBy('name')->get(['id', 'kuerzel', 'nummer', 'name']);
});

$saveStandortTags = function (): void {
    $validated = $this->validate([
        'standortTags' => ['array'],
        'standortTags.*' => ['nullable', 'string', 'max:255'],
    ]);

    foreach ($validated['standortTags'] as $standortId => $tag) {
        $tag = trim((string) $tag);

        if ($tag === '') {
            TicketStandortTag::query()->where('standort_id', (int) $standortId)->delete();

            continue;
        }

        TicketStandortTag::query()->updateOrCreate(
            ['standort_id' => (int) $standortId],
            ['tag' => $tag],
        );
    }

    Flux::toast(text: 'Standort-Tags gespeichert.', variant: 'success');
};

$saveGvpTags = function (): void {
    $validated = $this->validate([
        'gvpTags' => ['array'],
        'gvpTags.*' => ['nullable', 'string', 'max:255'],
    ]);

    foreach ($validated['gvpTags'] as $gvpId => $tag) {
        $tag = trim((string) $tag);

        if ($tag === '') {
            TicketGvpTag::query()->where('gvp_id', (int) $gvpId)->delete();

            continue;
        }

        TicketGvpTag::query()->updateOrCreate(
            ['gvp_id' => (int) $gvpId],
            ['tag' => $tag],
        );
    }

    Flux::toast(text: 'GVP-Tags gespeichert.', variant: 'success');
};

?>

<div class="space-y-6">
    <flux:text>
        Ordnen Sie Standorten und GVP-Einheiten Zammad-Tags zu. Beim Erstellen eines Tickets werden die Tags des Ticket-Kunden
        (Feld „Ticket von“) automatisch mitgesendet — nur wenn ein Tag hinterlegt ist.
    </flux:text>

    <flux:tab.group>
        <flux:tabs wire:model="activeSection">
            <flux:tab name="standort" icon="map-pin">Standorte</flux:tab>
            <flux:tab name="gvp" icon="building-office-2">GVP-Einheiten</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="standort">
            <form wire:submit="saveStandortTags" class="space-y-4">
                <flux:card class="overflow-hidden p-0">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Standort</flux:table.column>
                            <flux:table.column>Zammad-Tag</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($this->standorte as $standort)
                                <flux:table.row wire:key="standort-tag-{{ $standort->id }}">
                                    <flux:table.cell>{{ $standort->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:input
                                            wire:model="standortTags.{{ $standort->id }}"
                                            placeholder="z. B. standort-dortmund"
                                        />
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="2">
                                        <flux:text class="text-zinc-500">Keine Standorte vorhanden.</flux:text>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>

                @if ($this->standorte->isNotEmpty())
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">Standort-Tags speichern</flux:button>
                    </div>
                @endif
            </form>
        </flux:tab.panel>

        <flux:tab.panel name="gvp">
            <form wire:submit="saveGvpTags" class="space-y-4">
                <flux:card class="overflow-hidden p-0">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>GVP-Einheit</flux:table.column>
                            <flux:table.column>Zammad-Tag</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($this->gvps as $gvp)
                                <flux:table.row wire:key="gvp-tag-{{ $gvp->id }}">
                                    <flux:table.cell>
                                        {{ trim(($gvp->kuerzel ?? '').' '.($gvp->nummer ?? '').' '.$gvp->name) }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:input
                                            wire:model="gvpTags.{{ $gvp->id }}"
                                            placeholder="z. B. gvp-it"
                                        />
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="2">
                                        <flux:text class="text-zinc-500">Keine GVP-Einheiten vorhanden.</flux:text>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>

                @if ($this->gvps->isNotEmpty())
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">GVP-Tags speichern</flux:button>
                    </div>
                @endif
            </form>
        </flux:tab.panel>
    </flux:tab.group>
</div>
