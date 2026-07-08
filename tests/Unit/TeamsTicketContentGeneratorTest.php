<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiChatResult;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppTickets\Data\AppSettings;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketContentGenerator;
use Hwkdo\IntranetAppTickets\Services\TicketsAppSettingsStore;

it('generiert strukturierte Ticket-Inhalte über das KI-Gateway', function (): void {
    $gateway = Mockery::mock(IntranetAiGatewayInterface::class);
    $gateway->shouldReceive('chat')
        ->once()
        ->withArgs(function (array $messages, AiRequestContext $context): bool {
            return $context->appIdentifier === 'tickets'
                && $context->capability === AiCapability::Text;
        })
        ->andReturn(new AiChatResult(
            content: json_encode([
                'betreff' => 'Drucker defekt im 2. OG',
                'inhalt' => 'Der Nutzer meldet einen defekten Drucker im zweiten Obergeschoss.',
            ], JSON_UNESCAPED_UNICODE),
            rawJson: '{}',
        ));

    $settingsStore = Mockery::mock(TicketsAppSettingsStore::class);
    $settingsStore->shouldReceive('current')
        ->andReturn(new AppSettings(teamsTicketAiEnabled: true));

    $generator = new TeamsTicketContentGenerator($settingsStore, $gateway);

    $result = $generator->generate(
        rawContent: 'der drucker im 2. og ist defekt',
        displayName: 'Max Mustermann',
        sourceLabel: 'Teams',
        fallbackSubject: 'Fallback',
        fallbackBody: 'Fallback-Body',
    );

    expect($result->generatedByAi)->toBeTrue()
        ->and($result->subject)->toBe('Drucker defekt im 2. OG')
        ->and($result->body)->toContain('Drucker');
});

it('nutzt Fallback wenn KI deaktiviert ist', function (): void {
    $gateway = Mockery::mock(IntranetAiGatewayInterface::class);
    $gateway->shouldNotReceive('chat');

    $settingsStore = Mockery::mock(TicketsAppSettingsStore::class);
    $settingsStore->shouldReceive('current')
        ->andReturn(new AppSettings(teamsTicketAiEnabled: false));

    $generator = new TeamsTicketContentGenerator($settingsStore, $gateway);

    $result = $generator->generate(
        rawContent: 'test',
        displayName: null,
        sourceLabel: 'Teams',
        fallbackSubject: 'Fallback-Betreff',
        fallbackBody: 'Fallback-Body',
    );

    expect($result->generatedByAi)->toBeFalse()
        ->and($result->subject)->toBe('Fallback-Betreff')
        ->and($result->body)->toBe('Fallback-Body');
});
