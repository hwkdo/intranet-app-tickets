<?php

use function Livewire\Volt\{title};

title('Tickets - Meine Einstellungen');

?>

<x-intranet-app-tickets::tickets-layout heading="Meine Einstellungen" subheading="Persönliche Einstellungen für die Tickets App">
    @livewire('intranet-app-base::user-settings', ['appIdentifier' => 'tickets'])
</x-intranet-app-tickets::tickets-layout>
