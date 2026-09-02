@if ($pruefung_step === 1)
    <div class="space-y-4">
        <flux:heading size="sm">Wann findet die Prüfung statt?</flux:heading>
        <flux:date-picker
            wire:model="pruefung_datum"
            label="Prüfungsdatum"
            class="w-full"
            required
        />
        <flux:button type="button" variant="primary" wire:click="loadPruefungen" wire:loading.attr="disabled">
            Weiter
        </flux:button>
    </div>
@elseif ($pruefung_step === 2)
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="sm">Prüfungstermine wählen</flux:heading>
            <flux:button type="button" variant="ghost" size="sm" wire:click="pruefungZurueck">
                Zurück
            </flux:button>
        </div>

        <flux:text class="text-sm text-zinc-500">
            Datum: {{ \Illuminate\Support\Carbon::parse($pruefung_datum)->format('d.m.Y') }}
        </flux:text>

        @if ($pruefung_selected_pruefung_id !== null)
            <flux:callout variant="info" icon="information-circle">
                Es können nur Termine derselben Prüfung gemeinsam ausgewählt werden. Andere Prüfungen sind deaktiviert.
            </flux:callout>
        @else
            <flux:text class="text-sm text-zinc-500">
                Mehrere Termine derselben Prüfung können gemeinsam ausgewählt werden.
            </flux:text>
        @endif

        @if ($pruefung_termine === [])
            <flux:callout variant="warning" icon="exclamation-triangle">
                Keine Prüfungen an diesem Datum.
            </flux:callout>
        @else
            <div class="space-y-3">
                @foreach ($pruefung_termine as $termin)
                    @php
                        $selectedKeys = array_map('strval', $pruefung_selected_ids);
                        $isSelected = in_array((string) $termin['selection_key'], $selectedKeys, true);
                        $isDisabled = $pruefung_selected_pruefung_id !== null
                            && (int) ($termin['pruefung_id'] ?? 0) !== (int) $pruefung_selected_pruefung_id;
                        $raum = collect([
                            $termin['pruefungsort_name'],
                            $termin['gebaeudenummer'],
                            $termin['raumnummer'],
                        ])->filter(fn ($value) => $value !== null && $value !== '')->implode(', ');
                        if ($raum === '' && ($termin['termin_bezeichnung'] ?? '') !== '') {
                            $raum = $termin['termin_bezeichnung'];
                        }
                    @endphp
                    <button
                        type="button"
                        @if ($isDisabled) disabled @endif
                        wire:click="togglePruefungTermin({{ json_encode($termin['selection_key']) }})"
                        @class([
                            'w-full rounded-lg border p-4 text-left transition',
                            'border-accent bg-accent/5 ring-1 ring-accent' => $isSelected,
                            'border-zinc-200 hover:border-accent hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' => ! $isSelected && ! $isDisabled,
                            'cursor-not-allowed border-zinc-200 opacity-50 dark:border-zinc-700' => $isDisabled,
                        ])
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="font-medium">
                                {{ $termin['pruefung_bezeichnung'] !== '' ? $termin['pruefung_bezeichnung'] : $termin['termin_bezeichnung'] }}
                            </div>
                            @if ($isSelected)
                                <flux:badge color="green" size="sm">Ausgewählt</flux:badge>
                            @elseif ($isDisabled)
                                <flux:badge color="zinc" size="sm">Andere Prüfung</flux:badge>
                            @endif
                        </div>

                        @if ($raum !== '')
                            <div @class([
                                'mt-3 flex items-center gap-2 rounded-md border px-3 py-2',
                                'border-accent/50 bg-accent/15' => $isSelected,
                                'border-zinc-300 bg-zinc-900/[0.04] dark:border-white/25 dark:bg-white/10' => ! $isSelected,
                            ])>
                                <flux:icon icon="building-office-2" class="size-5 shrink-0 text-accent" />
                                <span class="text-base font-semibold tracking-tight text-zinc-800 dark:text-white">
                                    Raum: {{ $raum }}
                                </span>
                            </div>
                        @endif

                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            @if ($termin['termin_bezeichnung'] !== '' && $termin['pruefung_bezeichnung'] !== '' && $termin['termin_bezeichnung'] !== $raum)
                                {{ $termin['termin_bezeichnung'] }} ·
                            @endif
                            @if ($termin['uhrzeit_von'] !== '' || $termin['uhrzeit_bis'] !== '')
                                {{ $termin['uhrzeit_von'] }}@if ($termin['uhrzeit_bis'] !== '')–{{ $termin['uhrzeit_bis'] }}@endif
                            @endif
                        </div>
                        <div class="mt-1 text-sm text-zinc-500">
                            {{ $termin['ordnung'] }}
                            @if ($termin['anzahl_prueflinge'] !== null && $termin['anzahl_prueflinge'] !== '')
                                · {{ $termin['anzahl_prueflinge'] }} Teilnehmer
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>

            <flux:button
                type="button"
                variant="primary"
                wire:click="confirmPruefungTermine"
                wire:loading.attr="disabled"
                :disabled="count($pruefung_selected_ids) === 0"
            >
                Weiter{{ $pruefung_selected_ids !== [] ? ' ('.count($pruefung_selected_ids).')' : '' }}
            </flux:button>
        @endif
    </div>
@else
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="sm">Ticket-Daten prüfen und ergänzen</flux:heading>
            <flux:button type="button" variant="ghost" size="sm" wire:click="pruefungZurueck">
                Zurück
            </flux:button>
        </div>

        <flux:input wire:model="betreff" label="Betreff" required />

        @if (count($pruefungstermine) > 1)
            <div class="space-y-4">
                <flux:heading size="sm">Räume / Termine</flux:heading>
                <flux:input wire:model="pruefung_id" label="PrüfungID" required />
                @foreach ($pruefungstermine as $index => $eintrag)
                    <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">Raum {{ $index + 1 }}</flux:heading>
                        <flux:input
                            wire:model="pruefungstermine.{{ $index }}.pruefungstermin_id"
                            label="PrüfungsterminID"
                            required
                        />
                        <flux:input
                            wire:model="pruefungstermine.{{ $index }}.raeume"
                            label="Raum"
                            required
                        />
                        <flux:input
                            wire:model="pruefungstermine.{{ $index }}.anzahl_teilnehmer"
                            type="number"
                            min="0"
                            label="Anzahl Teilnehmer"
                            required
                        />
                    </div>
                @endforeach
            </div>

            <flux:date-picker wire:model="datum" label="Datum" class="w-full" required />
            <flux:textarea wire:model="gewerk" label="Gewerk (Ordnung)" rows="2" required />
            <flux:input wire:model="ansprechpartner" label="Ansprechpartner" required />
        @else
            <flux:input wire:model="pruefungstermin_id" label="PrüfungsterminID" required />
            <flux:input wire:model="pruefung_id" label="PrüfungID" required />
            <flux:date-picker wire:model="datum" label="Datum" class="w-full" required />
            <flux:textarea wire:model="gewerk" label="Gewerk (Ordnung)" rows="2" required />
            <flux:input wire:model="raeume" label="Räume" required />
            <flux:input wire:model="anzahl_teilnehmer" type="number" min="0" label="Anzahl Teilnehmer" required />
            <flux:input wire:model="ansprechpartner" label="Ansprechpartner" required />
        @endif

        <flux:textarea wire:model="verwendete_anwendungen" label="Verwendete Anwendungen" rows="3" required />
        <flux:textarea
            wire:model="weitere_wichtige_informationen"
            label="Weitere Wichtige Informationen"
            rows="3"
            placeholder="z.B. Namensschilder vorhanden, können abgeholt werden. O.ä."
        />

        <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">Sperre Prüfungsbenutzer ab</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Sperren im Sinne von den Schreibzugriff der Benutzer verhindern. Sie können ab dann nur noch Daten lesen.
            </flux:text>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:date-picker
                    wire:model="sperre_pruefungsbenutzer_ab_datum"
                    label="Datum"
                    class="w-full"
                />
                <flux:time-picker
                    wire:model="sperre_pruefungsbenutzer_ab_uhrzeit"
                    label="Uhrzeit"
                    time-format="24-hour"
                />
            </div>
        </div>

        <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">Löschung Prüfungsbenutzer ab</flux:heading>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:date-picker
                    wire:model="loeschung_pruefungsbenutzer_ab_datum"
                    label="Datum"
                    class="w-full"
                />
                <flux:time-picker
                    wire:model="loeschung_pruefungsbenutzer_ab_uhrzeit"
                    label="Uhrzeit"
                    time-format="24-hour"
                />
            </div>
        </div>
    </div>
@endif
