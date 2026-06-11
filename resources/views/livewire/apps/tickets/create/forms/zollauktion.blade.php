<flux:input wire:model="betreff" label="Auktionstitel" required />
<flux:input wire:model="anzahl" type="number" min="1" label="Anzahl" required />
<flux:textarea wire:model="inhalt" label="Beschreibung" rows="8" maxlength="3000" required />
<flux:input wire:model="etikettennummer" label="Etikettennummer (Itexia)" required />
<flux:select wire:model="foerderung" label="Förderung">
    <flux:select.option value="Ja">Ja, es bestehen Auflagen/Beschränkungen</flux:select.option>
    <flux:select.option value="Nein">Nein, es bestehen keine Auflagen/Beschränkungen</flux:select.option>
</flux:select>
<flux:input wire:model="mingebot" type="number" min="1" label="Mindestgebot" required />
<flux:input wire:model="kostenstellen" label="Kostenstelle" required />
<div class="grid gap-4 md:grid-cols-2">
    <flux:input wire:model="Hersteller" label="Hersteller" required />
    <flux:input wire:model="ArtTypBez" label="Artikelnr, Typ, Bezeichnung" required />
    <flux:input wire:model="Inventurnummer" label="Inventarnummer" required />
    <flux:input wire:model="Baujahr" label="Baujahr / Alter" required />
    <flux:input wire:model="Gewicht" label="Gewicht" required />
    <flux:input wire:model="Masse" label="Maße" required />
    <flux:input wire:model="Techdata" label="Technische Daten" required />
</div>
<flux:switch wire:model="cekenn" label="CE-Kennzeichnung" />
<flux:switch wire:model.live="wartung" label="Wartung / Prüfsiegel" />
@if ($wartung)
    <flux:input wire:model="wartung_date" type="date" label="Wartung / Prüfsiegel Datum" />
@endif
<flux:select wire:model="standort" label="Standort" variant="listbox" searchable>
    @foreach ($this->standorte as $standortOption)
        <flux:select.option value="{{ $standortOption->id }}">{{ $standortOption->name }}</flux:select.option>
    @endforeach
</flux:select>
<flux:select wire:model="ansprechpartner" label="Ansprechpartner" variant="listbox" searchable>
    @foreach ($this->employees as $employee)
        <flux:select.option value="{{ $employee->id }}">{{ $employee->name }}</flux:select.option>
    @endforeach
</flux:select>
@include('intranet-app-tickets::livewire.apps.tickets.create.partials.attachments')
