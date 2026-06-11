<flux:select wire:model="betreff" label="Betreff" placeholder="Bitte wählen">
    @foreach (['Flyer', 'Broschüre', 'Infoblatt', 'Plakat', 'Anzeige', 'Roll-up', 'Video', 'Radio', 'Eventmanagement', 'sonstiges'] as $option)
        <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
    @endforeach
</flux:select>
<flux:select wire:model="art" label="Typ" placeholder="Bitte wählen">
    <flux:select.option value="Änderung">Änderung</flux:select.option>
    <flux:select.option value="Neuanlage">Neuanlage</flux:select.option>
</flux:select>
<flux:select wire:model.live="geschaeftsbereich" label="Geschäftsbereich" placeholder="GB wählen">
    @foreach ($this->geschaeftsbereiche as $gb)
        <flux:select.option value="{{ $gb->id }}">{{ $gb->bezeichnung }}</flux:select.option>
    @endforeach
</flux:select>
<flux:select wire:model="fachbereich" label="Fachbereich" placeholder="Fachbereich wählen">
    @foreach ($this->fachbereiche as $fb)
        <flux:select.option value="{{ $fb->bezeichnung }}">{{ $fb->bezeichnung }}</flux:select.option>
    @endforeach
</flux:select>
<flux:select wire:model="auswahl" label="Zielgruppe" placeholder="Bitte wählen">
    @foreach (['Unternehmer', 'Auszubildende', 'Politik', 'Fort-/Weiterbildung', 'sonstiges'] as $option)
        <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
    @endforeach
</flux:select>
<flux:textarea wire:model="inhalt" label="Beschreibung" rows="8" maxlength="3000" required />
<flux:input wire:model="datum" type="date" label="Fertigstellung" min="{{ now()->addWeeks(12)->toDateString() }}" required />
<flux:textarea wire:model="bemerkung" label="Bemerkung" rows="2" maxlength="200" />
<flux:select wire:model="abgestimmt_mit" label="Genehmigung" variant="listbox" searchable placeholder="Vorgesetzten wählen">
    @foreach ($this->supervisors as $supervisor)
        <flux:select.option value="{{ $supervisor->id }}">{{ $supervisor->name }}</flux:select.option>
    @endforeach
</flux:select>
<flux:select wire:model="jahresplanung" label="Jahresplanung" placeholder="Bitte wählen">
    <flux:select.option value="Ja">Ja</flux:select.option>
    <flux:select.option value="Nein">Nein</flux:select.option>
</flux:select>
@include('intranet-app-tickets::livewire.apps.tickets.create.partials.attachments')
