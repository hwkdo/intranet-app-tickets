<flux:callout variant="info" icon="envelope" class="mb-4">
    Moodle-Tickets werden per E-Mail an die konfigurierte Stelle gesendet.
</flux:callout>
<flux:input wire:model="betreff" label="Betreff" required />
<flux:textarea wire:model="inhalt" label="Beschreibung" rows="8" maxlength="3000" required />
<flux:select wire:model="on_behalf_of_user_id" label="Ticket von" variant="listbox" searchable placeholder="Mitarbeiter wählen">
    @foreach ($this->employees as $employee)
        <flux:select.option value="{{ $employee->id }}">{{ $employee->name }}</flux:select.option>
    @endforeach
</flux:select>
@include('intranet-app-tickets::livewire.apps.tickets.create.partials.attachments')
