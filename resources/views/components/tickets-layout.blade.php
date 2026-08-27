@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

@php
    $canApproveTickets = auth()->check()
        && app(\Hwkdo\IntranetAppTickets\Services\TicketApprovalService::class)->userCanApproveAny(auth()->user());

    $defaultNavItems = [
        ['label' => 'Meine Tickets', 'href' => route('apps.tickets.index'), 'icon' => 'ticket', 'description' => 'Ticketübersicht anzeigen', 'buttonText' => 'Tickets öffnen'],
        ['label' => 'Neues Ticket', 'href' => route('apps.tickets.create.index'), 'icon' => 'plus-circle', 'description' => 'Support-Ticket erstellen', 'buttonText' => 'Ticket erstellen'],
        ['label' => 'KI-Chat', 'href' => route('apps.tickets.chat'), 'icon' => 'chat-bubble-left-right', 'description' => 'Tickets mit KI und MCP-Server verwalten', 'buttonText' => 'Chat öffnen', 'requiresAiUsage' => true],
        ...($canApproveTickets ? [[
            'label' => 'Genehmigungen',
            'href' => route('apps.tickets.approvals.index'),
            'icon' => 'check-badge',
            'description' => 'Offene Ticket-Genehmigungen bearbeiten',
            'buttonText' => 'Genehmigungen öffnen',
        ]] : []),
        ['label' => 'Meine Einstellungen', 'href' => route('apps.tickets.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Persönliche Einstellungen anpassen', 'buttonText' => 'Einstellungen öffnen'],
        ['label' => 'App-Info', 'href' => route('apps.tickets.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
        ['label' => 'Webhooks', 'href' => route('apps.tickets.webhooks.index'), 'icon' => 'bell', 'description' => 'Eingegangene Zammad-Webhooks', 'buttonText' => 'Webhooks öffnen', 'permission' => 'manage-app-tickets'],
        ['label' => 'Admin', 'href' => route('apps.tickets.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-tickets']
    ];

    $navItems = !empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('tickets');
@endphp

@if($customBgUrl)
    @push('app-styles')
    <style data-app-bg data-ts="{{ uniqid() }}">
        :root { --app-bg-image: url('{{ $customBgUrl }}'); }
    </style>
    @endpush
@endif

<x-intranet-app-base::app-layout
    app-identifier="tickets"
    :heading="$heading"
    :subheading="$subheading"
    :nav-items="$navItems"
>
    {{ $slot }}
</x-intranet-app-base::app-layout>
