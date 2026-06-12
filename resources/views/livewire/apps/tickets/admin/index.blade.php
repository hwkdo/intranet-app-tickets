<?php

use function Livewire\Volt\{state, title};

title('Tickets - Admin');

state(['activeTab' => 'hintergrundbild']);

?>

<x-intranet-app-tickets::tickets-layout heading="Tickets App" subheading="Admin">
    <flux:tab.group>
        <flux:tabs wire:model="activeTab">
            <flux:tab name="hintergrundbild" icon="photo">Hintergrundbild</flux:tab>
            <flux:tab name="kategorien" icon="tag">Kategorien</flux:tab>
            <flux:tab name="tags" icon="bookmark">Zammad-Tags</flux:tab>
            <flux:tab name="zammad-benutzer" icon="users">Zammad-Benutzer</flux:tab>
            <flux:tab name="einstellungen" icon="cog-6-tooth">Einstellungen</flux:tab>
            <flux:tab name="statistiken" icon="chart-bar">Statistiken</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="hintergrundbild">
            <div style="min-height: 400px;">
                @livewire('intranet-app-base::app-background-image', [
                    'appIdentifier' => 'tickets',
                ])
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="kategorien">
            <div style="min-height: 400px;">
                <livewire:apps.tickets.admin.categories />
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="tags">
            <div style="min-height: 400px;">
                <livewire:apps.tickets.admin.tag-settings />
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="zammad-benutzer">
            <div id="zammad-users-table" style="min-height: 400px;">
                @livewire(\Hwkdo\IntranetAppTickets\Livewire\Admin\ZammadUserRoles::class, key('tickets-admin-zammad-users'))
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="einstellungen">
            <div style="min-height: 400px;">
                @livewire('intranet-app-base::admin-settings', [
                    'appIdentifier' => 'tickets',
                    'settingsModelClass' => '\Hwkdo\IntranetAppTickets\Models\IntranetAppTicketsSettings',
                    'appSettingsClass' => '\Hwkdo\IntranetAppTickets\Data\AppSettings'
                ])
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="statistiken">
            <div style="min-height: 400px;">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">App-Statistiken</flux:heading>
                    <flux:text class="mb-6">
                        Übersicht über die Nutzung der Tickets App.
                    </flux:text>
                    
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Aktive Benutzer</flux:heading>
                            <flux:text size="xl" class="mt-2">42</flux:text>
                        </div>
                        
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Seitenaufrufe</flux:heading>
                            <flux:text size="xl" class="mt-2">1,234</flux:text>
                        </div>
                        
                        <div class="rounded-lg border p-4">
                            <flux:heading size="md">Letzte Aktivität</flux:heading>
                            <flux:text size="xl" class="mt-2">2 Min</flux:text>
                        </div>
                    </div>
                </flux:card>
            </div>
        </flux:tab.panel>
    </flux:tab.group>
</x-intranet-app-tickets::tickets-layout>
