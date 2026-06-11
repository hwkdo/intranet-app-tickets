<flux:switch wire:model.live="meisterbrief" label="Meisterbrief" />
@if (! $meisterbrief)
    <flux:input wire:model="Druckanzahl" type="number" min="1" label="Druckanzahl" required />
    <flux:select wire:model="druckseitig" label="Ein-/Beidseitig" placeholder="Wählen">
        <flux:select.option value="Einseitig">Einseitig</flux:select.option>
        <flux:select.option value="Beidseitig">Beidseitig</flux:select.option>
    </flux:select>
    <flux:select wire:model="farbe" label="Druckfarbe" placeholder="Wählen">
        <flux:select.option value="Schwarz/Weiss">Schwarz/Weiss</flux:select.option>
        <flux:select.option value="Farbig">Farbig</flux:select.option>
    </flux:select>
@endif
<flux:input wire:model="datum" type="date" label="Fertigstellung" min="{{ now()->toDateString() }}" required />
<flux:select wire:model="binden" label="Binden/Lochen/Heften" placeholder="Optional">
    @foreach (['Gebunden', 'Gelocht', 'Geheftet', 'Geheftet+Gelocht', 'OhneWeiterverarbeitung'] as $option)
        <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
    @endforeach
</flux:select>
<flux:select wire:model="sortieren" label="Sortieren/Stapeln" placeholder="Optional">
    <flux:select.option value="Sortiert">Sortiert (abc,abc,abc)</flux:select.option>
    <flux:select.option value="Stapeln">Stapeln (aaa,bbb,ccc)</flux:select.option>
</flux:select>
<flux:textarea wire:model="inhalt" label="Bemerkung" rows="8" maxlength="3000" required />
@include('intranet-app-tickets::livewire.apps.tickets.create.partials.attachments')
