<div data-tour="tickets-it-support-fields" class="space-y-6">
    <flux:input wire:model="betreff" label="Betreff" required />
    <flux:textarea wire:model="inhalt" label="Beschreibung" rows="8" maxlength="3000" required />
    <div data-tour="tickets-on-behalf">
        <flux:select wire:model="on_behalf_of_user_id" label="Ticket von" variant="listbox" searchable placeholder="Mitarbeiter wählen">
            @foreach ($this->employees as $employee)
                <flux:select.option value="{{ $employee->id }}">{{ $employee->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>
    @include('intranet-app-tickets::livewire.apps.tickets.create.partials.attachments')
</div>
