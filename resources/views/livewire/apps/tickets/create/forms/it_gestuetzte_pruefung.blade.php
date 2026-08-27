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
            <flux:heading size="sm">Prüfungstermin wählen</flux:heading>
            <flux:button type="button" variant="ghost" size="sm" wire:click="pruefungZurueck">
                Zurück
            </flux:button>
        </div>

        <flux:text class="text-sm text-zinc-500">
            Datum: {{ \Illuminate\Support\Carbon::parse($pruefung_datum)->format('d.m.Y') }}
        </flux:text>

        @if ($pruefung_termine === [])
            <flux:callout variant="warning" icon="exclamation-triangle">
                Keine Prüfungen an diesem Datum.
            </flux:callout>
        @else
            <div class="space-y-3">
                @foreach ($pruefung_termine as $termin)
                    <button
                        type="button"
                        wire:click="selectPruefungTermin({{ $termin['termin_id'] }})"
                        class="w-full rounded-lg border border-zinc-200 p-4 text-left transition hover:border-accent hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    >
                        <div class="font-medium">
                            {{ $termin['pruefung_bezeichnung'] !== '' ? $termin['pruefung_bezeichnung'] : $termin['termin_bezeichnung'] }}
                        </div>
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            @if ($termin['termin_bezeichnung'] !== '' && $termin['pruefung_bezeichnung'] !== '')
                                {{ $termin['termin_bezeichnung'] }} ·
                            @endif
                            @if ($termin['uhrzeit_von'] !== '' || $termin['uhrzeit_bis'] !== '')
                                {{ $termin['uhrzeit_von'] }}@if ($termin['uhrzeit_bis'] !== '')–{{ $termin['uhrzeit_bis'] }}@endif ·
                            @endif
                            {{ collect([$termin['pruefungsort_name'], $termin['gebaeudenummer'], $termin['raumnummer']])->filter()->implode(', ') }}
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
        <flux:input wire:model="pruefungstermin_id" label="PrüfungsterminID" required />
        <flux:date-picker wire:model="datum" label="Datum" class="w-full" required />
        <flux:textarea wire:model="gewerk" label="Gewerk (Ordnung)" rows="2" required />
        <flux:input wire:model="raeume" label="Räume" required />
        <flux:input wire:model="anzahl_teilnehmer" type="number" min="0" label="Anzahl Teilnehmer" required />
        <flux:input wire:model="ansprechpartner" label="Ansprechpartner" required />
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
