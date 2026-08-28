<?php

use function Livewire\Volt\{title};

title('Tickets - Bedienungsanleitung');

?>

<div>
    <x-intranet-app-tickets::tickets-layout heading="Bedienungsanleitung" subheading="Schritt-für-Schritt-Anleitung zur Tickets-App">
        @livewire('intranet-app-base::manual-show', ['manualKey' => 'tickets.onboarding'])
    </x-intranet-app-tickets::tickets-layout>
</div>
