<?php

use function Livewire\Volt\title;

title('Benachrichtigungen - Tickets');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Benachrichtigungen" subheading="Benachrichtigungseinstellungen für die Tickets-App">
        @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'tickets'])
    </x-intranet-app-tickets::tickets-layout>
</div>
