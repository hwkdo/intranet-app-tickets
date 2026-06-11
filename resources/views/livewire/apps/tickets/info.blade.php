<?php

use function Livewire\Volt\{title};

title('Tickets - App-Info');

?>

<x-intranet-app-tickets::tickets-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
    @livewire('intranet-app-base::app-info', ['appIdentifier' => 'tickets'])
</x-intranet-app-tickets::tickets-layout>
