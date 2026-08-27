<div class="space-y-3" data-tour="tickets-attachments">
    <flux:heading size="sm">Anhänge</flux:heading>

    @foreach ($attachments as $index => $attachment)
        <div wire:key="attachment-{{ $index }}" class="space-y-1">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <flux:input type="file" wire:model="attachments.{{ $index }}" />
                    @if ($attachment)
                        <flux:text class="mt-1 truncate text-sm text-zinc-500">
                            {{ $attachment->getClientOriginalName() }}
                        </flux:text>
                    @endif
                </div>
                @if (count($attachments) > 1)
                    <flux:button
                        type="button"
                        wire:click="removeAttachment({{ $index }})"
                        variant="danger"
                        size="sm"
                        icon="minus"
                    />
                @endif
            </div>
            <flux:text wire:loading wire:target="attachments.{{ $index }}" class="text-sm text-zinc-500">
                Anhang wird hochgeladen…
            </flux:text>
        </div>
    @endforeach

    <flux:button type="button" wire:click="addAttachment" variant="ghost" size="sm" icon="plus" data-tour="tickets-add-attachment">
        Anhang hinzufügen
    </flux:button>

    @error('attachments')
        <flux:text class="text-sm text-red-500">{{ $message }}</flux:text>
    @enderror
    @error('attachments.*')
        <flux:text class="text-sm text-red-500">{{ $message }}</flux:text>
    @enderror
</div>
