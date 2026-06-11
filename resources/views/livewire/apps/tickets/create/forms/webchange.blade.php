<flux:input wire:model="betreff" label="Betreff" required />
<flux:select wire:model="auswahl" label="Typ" placeholder="Bitte wählen">
    <flux:select.option value="Änderung">Änderung</flux:select.option>
    <flux:select.option value="Neuanlage">Neuanlage</flux:select.option>
</flux:select>
<flux:input wire:model="url" type="url" label="URL" required />
<flux:textarea wire:model="inhalt" label="Beschreibung" rows="8" maxlength="3000" required />
<flux:select wire:model="abgestimmt_mit" label="Abgestimmt mit" variant="listbox" searchable placeholder="Vorgesetzten wählen">
    @foreach ($this->supervisors as $supervisor)
        <flux:select.option value="{{ $supervisor->id }}">{{ $supervisor->name }}</flux:select.option>
    @endforeach
</flux:select>
@include('intranet-app-tickets::livewire.apps.tickets.create.partials.attachments')
