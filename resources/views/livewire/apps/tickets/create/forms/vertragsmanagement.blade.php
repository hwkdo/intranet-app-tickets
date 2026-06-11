<flux:select wire:model.live="betreff" label="Typ" placeholder="Bitte wählen">
    <flux:select.option value="Vertragsgestaltung">Vertragserstellung</flux:select.option>
    <flux:select.option value="Vertragspruefung">Vertragsprüfung</flux:select.option>
</flux:select>
@if ($betreff === 'Vertragspruefung')
    <flux:select wire:model="vertragstyp" label="Vertragstyp" placeholder="Bitte wählen">
        <flux:select.option value="Neuvertrag">Neuvertrag</flux:select.option>
        <flux:select.option value="Bestandsvertrag">Bestandsvertrag</flux:select.option>
    </flux:select>
@endif
<flux:input wire:model="betreff2" label="Betreff" required />
<flux:textarea wire:model="inhalt" label="Kurzbeschreibung" rows="8" maxlength="3000" required />
@include('intranet-app-tickets::livewire.apps.tickets.create.partials.attachments')
