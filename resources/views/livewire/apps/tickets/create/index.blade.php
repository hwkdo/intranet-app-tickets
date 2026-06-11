<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use function Livewire\Volt\{computed, state, title};

state(['categorySlug' => '']);

$categories = computed(fn () => TicketCategory::query()
    ->where('active', true)
    ->orderBy('sort_order')
    ->get());

$continue = function (): void {
    $this->validate([
        'categorySlug' => ['required', 'exists:intranet_app_ticket_categories,slug'],
    ]);

    $this->redirect(route('apps.tickets.create.form', $this->categorySlug), navigate: true);
};

title('Neues Ticket');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Neues Ticket" subheading="Ticketart wählen">
        <div class="mx-auto max-w-2xl space-y-6">
            <flux:callout variant="secondary" icon="information-circle">
                Wählen Sie die passende Ticketart und füllen anschließend das Formular aus.
            </flux:callout>

            <flux:select wire:model="categorySlug" label="Ticketart" placeholder="Bitte wählen">
                @foreach ($this->categories as $category)
                    <flux:select.option value="{{ $category->slug }}">{{ $category->label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button wire:click="continue" variant="primary" icon="arrow-right">
                Weiter zum Formular
            </flux:button>
        </div>
    </x-intranet-app-tickets::tickets-layout>
</div>
