<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketQuotedSenderResolver;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketUserResolver;
use Hwkdo\MsGraphLaravel\Services\TeamsForwardedMessageSenderLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('intranet-app-tickets.user_model', User::class);
});

it('returns the intranet user when azure id is already known', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'socialite_id' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
        'vorname' => 'Markus',
        'nachname' => 'Lubritz',
    ]);

    $lookup = $this->mock(TeamsForwardedMessageSenderLookup::class, function ($mock): void {
        $mock->shouldReceive('lookup')->never();
    });

    $resolver = new TeamsTicketQuotedSenderResolver(app(TeamsTicketUserResolver::class));

    $resolved = $resolver->resolve(
        quotedSenderAzureId: '9d0ba845-db64-4977-9f43-3a244a4dab1c',
        quotedSenderName: 'Lubritz, Markus',
        actorAzureUserId: 'azure-max',
        quotedText: 'Hallo Alex, bitte die Seite aktualisieren.',
        excludeConversationId: 'conv-dm',
    );

    expect($resolved?->getAuthIdentifier())->toBe($user->id);
});

it('falls back to graph lookup when forward metadata has no sender', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'socialite_id' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
        'vorname' => 'Markus',
        'nachname' => 'Lubritz',
    ]);

    $this->mock(TeamsForwardedMessageSenderLookup::class, function ($mock): void {
        $mock->shouldReceive('lookup')
            ->once()
            ->with(
                'azure-max',
                'Hallo Alex, bitte die Seite aktualisieren.',
                'conv-dm',
            )
            ->andReturn([
                'azureUserId' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
                'displayName' => 'Lubritz, Markus',
            ]);
    });

    $resolver = new TeamsTicketQuotedSenderResolver(app(TeamsTicketUserResolver::class));

    $resolved = $resolver->resolve(
        quotedSenderAzureId: null,
        quotedSenderName: null,
        actorAzureUserId: 'azure-max',
        quotedText: 'Hallo Alex, bitte die Seite aktualisieren.',
        excludeConversationId: 'conv-dm',
    );

    expect($resolved?->getAuthIdentifier())->toBe($user->id);
});

it('returns null when graph lookup also fails to find a sender', function (): void {
    User::factory()->create([
        'active' => true,
        'username' => 'max',
        'socialite_id' => 'azure-max',
    ]);

    $this->mock(TeamsForwardedMessageSenderLookup::class, function ($mock): void {
        $mock->shouldReceive('lookup')
            ->once()
            ->andReturn(['azureUserId' => null, 'displayName' => null]);
    });

    $resolver = new TeamsTicketQuotedSenderResolver(app(TeamsTicketUserResolver::class));

    expect($resolver->resolve(
        quotedSenderAzureId: null,
        quotedSenderName: null,
        actorAzureUserId: 'azure-max',
        quotedText: 'Unbekannter Forward-Text',
        excludeConversationId: 'conv-dm',
    ))->toBeNull();
});
