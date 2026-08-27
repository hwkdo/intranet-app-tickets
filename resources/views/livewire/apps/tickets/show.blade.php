<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Support\TicketTourDemo;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, mount, on, state, title};

state([
    'userId' => null,
    'ticketId' => null,
    'replyBody' => '',
    'tourDemo' => false,
]);

mount(function (int|string $ticketId, ZammadTicketService $ticketService, TicketReadStateService $readStateService) {
    $this->userId = Auth::id();
    $this->ticketId = (int) $ticketId;
    $this->tourDemo = TicketTourDemo::isActive() && TicketTourDemo::isDemoTicketId((int) $ticketId);

    if ($this->tourDemo) {
        return;
    }

    $ticket = $ticketService->getTicketForUser(Auth::user(), (int) $ticketId);

    abort_if($ticket === null, 404);

    $articles = $ticketService->getPublicArticlesForUser(Auth::user(), (int) $ticketId);
    $lastArticleId = collect($articles)->last()['id'] ?? null;

    $readStateService->markRead(Auth::user(), (int) $ticketId, $lastArticleId !== null ? (int) $lastArticleId : null);
});

$ticket = computed(function () {
    if ($this->tourDemo) {
        return TicketTourDemo::demoTicket((int) $this->ticketId);
    }

    return app(ZammadTicketService::class)->getTicketForUser(Auth::user(), (int) $this->ticketId);
});

$articles = computed(function () {
    if ($this->tourDemo) {
        return collect(TicketTourDemo::demoArticles((int) $this->ticketId));
    }

    return collect(
        app(ZammadTicketService::class)->getPublicArticlesForUser(Auth::user(), (int) $this->ticketId)
    );
});

$owner = computed(function () {
    if ($this->tourDemo) {
        return [
            'firstname' => 'Max',
            'lastname' => 'Mustermann',
        ];
    }

    $ownerId = $this->ticket['owner_id'] ?? null;

    if ($ownerId === null) {
        return null;
    }

    return app(ZammadTicketService::class)->getZammadUser((int) $ownerId);
});

$sendReply = function (ZammadTicketService $ticketService) {
    $this->validate([
        'replyBody' => ['required', 'string', 'min:1'],
    ]);

    if ($this->tourDemo) {
        $this->replyBody = '';

        Flux::toast(
            heading: 'Demo',
            text: 'In der Tour wird keine echte Antwort gesendet.',
            variant: 'info',
        );

        return;
    }

    $ticketService->replyToTicket(Auth::user(), (int) $this->ticketId, $this->replyBody);

    $this->replyBody = '';
    unset($this->articles);

    Flux::toast(
        heading: 'Antwort gesendet',
        text: 'Ihre Nachricht wurde an den Support übermittelt.',
        variant: 'success',
    );
};

on(['echo-private:App.Models.User.{userId},.ticket.updated' => function (array $event) {
    if ((int) ($event['ticket_id'] ?? 0) === (int) $this->ticketId) {
        Flux::toast(
            heading: 'Neues Update',
            text: 'Dieses Ticket hat ein neues Update erhalten.',
            variant: 'info',
        );
        unset($this->ticket, $this->articles, $this->owner);
    }
}]);

title(fn () => 'Ticket #'.($this->ticket['number'] ?? $this->ticketId));

?>

<div>
    <x-intranet-app-tickets::tickets-layout
        heading="Ticket #{{ $this->ticket['number'] ?? $ticketId }}"
        :subheading="$this->ticket['title'] ?? ''"
    >
        @if ($tourDemo)
            <flux:callout variant="secondary" icon="map" class="mb-4">
                Demo-Ticket für die Produkt-Tour — Inhalte sind Beispiele.
            </flux:callout>
        @endif

        <div class="space-y-6" data-tour="tickets-show">
            <div class="flex flex-wrap items-center gap-2" data-tour="tickets-show-meta">
                <flux:badge>{{ $this->ticket['state'] ?? 'unbekannt' }}</flux:badge>
                @if ($this->owner)
                    <flux:text class="text-sm text-zinc-500">
                        Bearbeiter: {{ trim(($this->owner['firstname'] ?? '').' '.($this->owner['lastname'] ?? '')) }}
                    </flux:text>
                @endif
            </div>

            <div class="space-y-4" data-tour="tickets-show-articles">
                @foreach ($this->articles as $article)
                    <flux:card wire:key="article-{{ $article['id'] }}" class="glass-card">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <flux:text class="font-medium">
                                    {{ $article['sender'] === 'Agent' ? 'Support' : 'Sie' }}
                                </flux:text>
                                <flux:text class="text-sm text-zinc-500">
                                    {{ isset($article['created_at']) ? \Illuminate\Support\Carbon::parse($article['created_at'])->format('d.m.Y H:i') : '' }}
                                </flux:text>
                            </div>

                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! $article['body'] ?? '' !!}
                            </div>

                            @if (! empty($article['attachments']))
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($article['attachments'] as $attachment)
                                        @if ($tourDemo)
                                            <flux:badge size="sm" icon="paper-clip">
                                                {{ $attachment['filename'] ?? 'Anhang' }}
                                            </flux:badge>
                                        @else
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="paper-clip"
                                                :href="route('apps.tickets.attachments.download', [
                                                    'ticketId' => $ticketId,
                                                    'articleId' => $article['id'],
                                                    'attachmentId' => $attachment['id'],
                                                ])"
                                            >
                                                {{ $attachment['filename'] ?? 'Anhang' }}
                                            </flux:button>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>

            <flux:card class="glass-card" data-tour="tickets-reply">
                <form wire:submit="sendReply" class="space-y-4">
                    <flux:heading size="sm">Antworten</flux:heading>
                    <flux:textarea
                        wire:model="replyBody"
                        placeholder="Ihre Nachricht an den Support..."
                        rows="5"
                    />
                    <div class="flex items-center justify-between gap-3">
                        <flux:button variant="ghost" :href="route('apps.tickets.index')" wire:navigate>
                            Zurück zur Übersicht
                        </flux:button>
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" data-tour="tickets-reply-submit">
                            <span wire:loading.remove>Antwort senden</span>
                            <span wire:loading>Wird gesendet...</span>
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>
    </x-intranet-app-tickets::tickets-layout>
</div>
