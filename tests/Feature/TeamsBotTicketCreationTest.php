<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Database\Seeders\TicketCategorySeeder;
use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Jobs\CreateTicketFromTeamsMessageJob;
use Hwkdo\IntranetAppTickets\Listeners\HandleTeamsBotTicketCommand;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Data\GeneratedTeamsTicketContent;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketContentGenerator;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Hwkdo\MsGraphLaravel\Services\TeamsChatMessageService;
use Hwkdo\IntranetAppTeamsBot\Data\TeamsBotIncomingMessage;
use Hwkdo\IntranetAppTeamsBot\Events\TeamsBotMessageReceived;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed(TicketCategorySeeder::class);

    Permission::findOrCreate('see-app-tickets');
    Permission::findOrCreate('manage-app-tickets');
});

function teamsIncomingMessage(string $text, array $activityOverrides = []): TeamsBotIncomingMessage
{
    return TeamsBotIncomingMessage::fromWebhook(
        'message',
        array_merge([
            'text' => $text,
            'conversation' => ['id' => 'conv-dm', 'conversationType' => 'personal'],
            'from' => ['userPrincipalName' => 'max@example.com', 'name' => 'Max Mustermann'],
            'id' => 'msg-1',
        ], $activityOverrides),
        ['conversationId' => 'conv-dm', 'userAadId' => 'azure-max'],
        'azure-max',
    );
}

it('dispatches the ticket job and returns an acknowledgement for a command', function (): void {
    Queue::fake();

    $ack = app(HandleTeamsBotTicketCommand::class)->handle(
        new TeamsBotMessageReceived(teamsIncomingMessage('erstelle mir ein Ticket, dass der Drucker kaputt ist')),
    );

    expect($ack)->toBeString()->and($ack)->toContain('Ticket');

    Queue::assertPushed(CreateTicketFromTeamsMessageJob::class, function (CreateTicketFromTeamsMessageJob $job): bool {
        return $job->upn === 'max@example.com' && str_contains($job->rawContent, 'Drucker');
    });
});

it('asks for a description when the command has no content', function (): void {
    Queue::fake();

    $ack = app(HandleTeamsBotTicketCommand::class)->handle(
        new TeamsBotMessageReceived(teamsIncomingMessage('erstelle ein Ticket')),
    );

    expect($ack)->toBeString()->and($ack)->toContain('beschreibe');

    Queue::assertNothingPushed();
});

it('creates a ticket from a quoted message when the command only says dafür', function (): void {
    Queue::fake();

    $ack = app(HandleTeamsBotTicketCommand::class)->handle(
        new TeamsBotMessageReceived(teamsIncomingMessage(
            text: '<blockquote><div itemprop="comment">Der Drucker im 2. OG druckt nur leere Seiten.</div></blockquote><p>erstelle ein Ticket dafür</p>',
            activityOverrides: [],
        )),
    );

    expect($ack)->toBeString()->and($ack)->toContain('Ticket');

    Queue::assertPushed(CreateTicketFromTeamsMessageJob::class, function (CreateTicketFromTeamsMessageJob $job): bool {
        return str_contains($job->rawContent, 'Drucker im 2. OG')
            && ! str_contains(mb_strtolower($job->rawContent), 'dafür')
            && $job->contentFromQuote === true;
    });
});

it('creates a ticket for the original author when a direct message was forwarded to the bot', function (): void {
    Queue::fake();

    $ack = app(HandleTeamsBotTicketCommand::class)->handle(
        new TeamsBotMessageReceived(teamsIncomingMessage(
            text: 'erstelle mir ein ticket dafür',
            activityOverrides: [
                'attachments' => [
                    [
                        'contentType' => 'text/html',
                        'content' => <<<'HTML'
<blockquote itemscope itemtype="http://schema.org/Message">
<div itemprop="sender" itemscope itemtype="http://schema.org/Person">
<span itemprop="name">Lubritz, Markus</span>
</div>
<div itemprop="comment">Mein Laptop startet nicht mehr.</div>
</blockquote>
<p>erstelle mir ein ticket dafür</p>
HTML,
                    ],
                    [
                        'contentType' => 'messageReference',
                        'content' => json_encode([
                            'messagePreview' => 'Mein Laptop startet nicht mehr.',
                            'messageSender' => [
                                'user' => [
                                    'id' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
                                    'displayName' => 'Lubritz, Markus',
                                ],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        )),
    );

    expect($ack)->toBeString()->and($ack)->toContain('Ticket');

    Queue::assertPushed(CreateTicketFromTeamsMessageJob::class, function (CreateTicketFromTeamsMessageJob $job): bool {
        return str_contains($job->rawContent, 'Weitergeleitete Nachricht')
            && str_contains($job->rawContent, 'Laptop startet nicht')
            && $job->contentFromQuote === true
            && $job->quotedSenderAzureId === '9d0ba845-db64-4977-9f43-3a244a4dab1c';
    });
});

it('passes quoted text without sender metadata for skype forward payloads', function (): void {
    Queue::fake();

    $ack = app(HandleTeamsBotTicketCommand::class)->handle(
        new TeamsBotMessageReceived(teamsIncomingMessage(
            text: 'erstelle ein ticket',
            activityOverrides: [
                'attachments' => [
                    [
                        'contentType' => 'text/html',
                        'content' => <<<'HTML'
<p>erstelle ein ticket</p>
<blockquote itemtype="http://schema.skype.com/Forward">
<p>Hallo Alex, auf folgender Seite stehen noch falsche Informationen.</p>
<p>Ich wollte gerade ein Webchange Ticket öffnen.</p>
</blockquote>
HTML,
                    ],
                ],
            ],
        )),
    );

    expect($ack)->toBeString()->and($ack)->toContain('Ticket');

    Queue::assertPushed(CreateTicketFromTeamsMessageJob::class, function (CreateTicketFromTeamsMessageJob $job): bool {
        return $job->contentFromQuote === true
            && $job->quotedSenderAzureId === null
            && $job->quotedSenderName === null
            && filled($job->quotedText)
            && str_contains($job->quotedText, 'Hallo Alex');
    });
});

it('creates a ticket from a simple forwarded blockquote in direct messages when sender metadata is present', function (): void {
    Queue::fake();

    $ack = app(HandleTeamsBotTicketCommand::class)->handle(
        new TeamsBotMessageReceived(teamsIncomingMessage(
            text: '<blockquote><div itemprop="comment">Mein Laptop startet nicht mehr.</div></blockquote><p>erstelle ein Ticket dafür</p>',
            activityOverrides: [
                'attachments' => [
                    [
                        'contentType' => 'messageReference',
                        'content' => json_encode([
                            'messagePreview' => 'Mein Laptop startet nicht mehr.',
                            'messageSender' => [
                                'user' => [
                                    'id' => '11111111-2222-3333-4444-555555555555',
                                    'displayName' => 'Anna Beispiel',
                                ],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        )),
    );

    expect($ack)->toBeString()->and($ack)->toContain('Ticket');

    Queue::assertPushed(CreateTicketFromTeamsMessageJob::class, function (CreateTicketFromTeamsMessageJob $job): bool {
        return $job->contentFromQuote === true
            && $job->quotedSenderAzureId === '11111111-2222-3333-4444-555555555555';
    });
});

it('creates a ticket from a messageReference quote when the command only says dafür in a group chat', function (): void {
    Queue::fake();

    $ack = app(HandleTeamsBotTicketCommand::class)->handle(
        new TeamsBotMessageReceived(teamsIncomingMessage(
            text: '<at>Bot</at> erstelle ein Ticket dafür',
            activityOverrides: [
                'conversation' => ['id' => '19:group@thread.v2', 'conversationType' => 'groupChat'],
                'attachments' => [
                    [
                        'contentType' => 'messageReference',
                        'content' => json_encode([
                            'messagePreview' => 'WLAN im Besprechungsraum fällt ständig aus',
                            'messageSender' => [
                                'user' => [
                                    'id' => '11111111-2222-3333-4444-555555555555',
                                    'displayName' => 'Anna Beispiel',
                                ],
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        )),
    );

    expect($ack)->toBeString()->and($ack)->toContain('Ticket');

    Queue::assertPushed(CreateTicketFromTeamsMessageJob::class, function (CreateTicketFromTeamsMessageJob $job): bool {
        return str_contains($job->rawContent, 'WLAN im Besprechungsraum')
            && $job->contentFromQuote === true;
    });
});

it('ignores non ticket messages', function (): void {
    Queue::fake();

    $ack = app(HandleTeamsBotTicketCommand::class)->handle(
        new TeamsBotMessageReceived(teamsIncomingMessage('Hallo Bot, wie gehts?')),
    );

    expect($ack)->toBeNull();

    Queue::assertNothingPushed();
});

it('refuses to create a ticket when the forwarded message author cannot be resolved', function (): void {
    $actor = User::factory()->create([
        'active' => true,
        'username' => 'max',
        'socialite_id' => 'azure-max',
        'name' => 'Max Mustermann',
    ]);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $this->mock(TeamsTicketContentGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->never();
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->never();
    });

    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: 'max@example.com',
        azureUserId: 'azure-max',
        displayName: 'Max Mustermann',
        rawContent: "Weitergeleitete Nachricht von Externer Kollege:\nMein Laptop startet nicht mehr",
        fallbackSubject: 'Mein Laptop startet nicht mehr',
        fallbackBody: "Weitergeleitete Nachricht von Externer Kollege:\nMein Laptop startet nicht mehr",
        sourceLabel: 'Microsoft Teams (Direktnachricht)',
        contentFromQuote: true,
        quotedSenderAzureId: '99999999-9999-9999-9999-999999999999',
        quotedSenderName: 'Externer Kollege',
        activity: [],
        conversationRef: [],
    ));

    expect(TicketRequest::query()->count())->toBe(0);
});

it('creates a ticket for the original author when graph lookup finds the forward sender', function (): void {
    User::factory()->create([
        'active' => true,
        'username' => 'max',
        'socialite_id' => 'azure-max',
        'name' => 'Max Mustermann',
    ]);

    $quotedUser = User::factory()->create([
        'active' => true,
        'username' => 'markus',
        'socialite_id' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
        'vorname' => 'Markus',
        'nachname' => 'Lubritz',
    ]);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $this->mock(TeamsChatMessageService::class, function ($mock): void {
        $mock->shouldReceive('lookupForwardedMessageSender')
            ->once()
            ->with(
                'azure-max',
                'Hallo Alex, auf folgender Seite stehen noch falsche Informationen.',
                'conv-dm',
            )
            ->andReturn([
                'azureUserId' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
                'displayName' => 'Lubritz, Markus',
            ]);
    });

    $this->mock(TeamsTicketContentGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(new GeneratedTeamsTicketContent(
            subject: 'Falsche Informationen auf Webseite',
            body: 'Auf einer Intranet-Seite stehen veraltete Informationen.',
        ));
    });

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(779);
        $mock->shouldReceive('addTagsToTicket');
    });

    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: 'max@example.com',
        azureUserId: 'azure-max',
        displayName: 'Max Mustermann',
        rawContent: "Weitergeleitete Nachricht:\nHallo Alex, auf folgender Seite stehen noch falsche Informationen.",
        fallbackSubject: 'Falsche Informationen',
        fallbackBody: "Weitergeleitete Nachricht:\nHallo Alex, auf folgender Seite stehen noch falsche Informationen.",
        sourceLabel: 'Microsoft Teams (Direktnachricht)',
        contentFromQuote: true,
        quotedSenderAzureId: null,
        quotedSenderName: null,
        quotedText: 'Hallo Alex, auf folgender Seite stehen noch falsche Informationen.',
        activity: ['conversation' => ['id' => 'conv-dm', 'conversationType' => 'personal']],
        conversationRef: ['conversationId' => 'conv-dm'],
    ));

    $request = TicketRequest::query()->first();

    expect($request)->not->toBeNull()
        ->and($request->on_behalf_of_user_id)->toBe($quotedUser->id);
});

it('creates a zammad ticket for the forwarded message author in a direct message', function (): void {
    $actor = User::factory()->create([
        'active' => true,
        'username' => 'max',
        'socialite_id' => 'azure-max',
        'name' => 'Max Mustermann',
    ]);

    $quotedUser = User::factory()->create([
        'active' => true,
        'username' => 'markus',
        'socialite_id' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
        'vorname' => 'Markus',
        'nachname' => 'Lubritz',
    ]);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $this->mock(TeamsTicketContentGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(new GeneratedTeamsTicketContent(
            subject: 'Laptop startet nicht',
            body: 'Der Nutzer meldet, dass sein Laptop nicht mehr startet.',
        ));
    });

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(778);
        $mock->shouldReceive('addTagsToTicket');
    });

    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: 'max@example.com',
        azureUserId: 'azure-max',
        displayName: 'Max Mustermann',
        rawContent: "Weitergeleitete Nachricht von Lubritz, Markus:\nMein Laptop startet nicht mehr",
        fallbackSubject: 'Mein Laptop startet nicht mehr',
        fallbackBody: "Weitergeleitete Nachricht von Lubritz, Markus:\nMein Laptop startet nicht mehr",
        sourceLabel: 'Microsoft Teams (Direktnachricht)',
        contentFromQuote: true,
        quotedSenderAzureId: '9d0ba845-db64-4977-9f43-3a244a4dab1c',
        quotedSenderName: 'Lubritz, Markus',
        activity: [],
        conversationRef: [],
    ));

    $request = TicketRequest::query()->first();

    expect($request)->not->toBeNull()
        ->and($request->requested_by_user_id)->toBe($actor->id)
        ->and($request->on_behalf_of_user_id)->toBe($quotedUser->id)
        ->and($request->body)->toContain('Der Nutzer meldet, dass sein Laptop nicht mehr startet.')
        ->and($request->body)->toContain('Originaltext aus Microsoft Teams:')
        ->and($request->body)->toContain('Weitergeleitete Nachricht von Lubritz, Markus:')
        ->and($request->body)->toContain('Erstellt für: Lubritz, Markus')
        ->and($request->body)->toContain('Erstellt von: Max Mustermann');
});

it('creates a zammad ticket for the quoted message author when a quote was used', function (): void {
    $actor = User::factory()->create([
        'active' => true,
        'username' => 'max',
        'socialite_id' => 'azure-max',
        'name' => 'Max Mustermann',
    ]);

    $quotedUser = User::factory()->create([
        'active' => true,
        'username' => 'anna',
        'socialite_id' => '11111111-2222-3333-4444-555555555555',
        'name' => 'Anna Beispiel',
    ]);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $this->mock(TeamsTicketContentGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(new GeneratedTeamsTicketContent(
            subject: 'Computer defekt',
            body: 'Der Nutzer meldet einen defekten Computer.',
        ));
    });

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(777);
        $mock->shouldReceive('addTagsToTicket');
    });

    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: 'max@example.com',
        azureUserId: 'azure-max',
        displayName: 'Max Mustermann',
        rawContent: "Zitierte Nachricht von Anna Beispiel:\nMein Computer ist kaputt",
        fallbackSubject: 'Mein Computer ist kaputt',
        fallbackBody: "Zitierte Nachricht von Anna Beispiel:\nMein Computer ist kaputt",
        sourceLabel: 'Microsoft Teams (Gruppenchat)',
        contentFromQuote: true,
        quotedSenderAzureId: '11111111-2222-3333-4444-555555555555',
        quotedSenderName: 'Anna Beispiel',
        activity: [],
        conversationRef: [],
    ));

    $request = TicketRequest::query()->first();

    expect($request)->not->toBeNull()
        ->and($request->requested_by_user_id)->toBe($actor->id)
        ->and($request->on_behalf_of_user_id)->toBe($quotedUser->id)
        ->and($request->body)->toContain('Der Nutzer meldet einen defekten Computer.')
        ->and($request->body)->toContain('Originaltext aus Microsoft Teams:')
        ->and($request->body)->toContain('Zitierte Nachricht von Anna Beispiel:')
        ->and($request->body)->toContain('Erstellt für: Anna Beispiel')
        ->and($request->body)->toContain('Erstellt von: Max Mustermann');
});

it('does not duplicate the original teams text when ai formatting is disabled', function (): void {
    User::factory()->create(['active' => true, 'username' => 'max', 'socialite_id' => 'azure-max']);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $rawContent = 'der drucker im 2. og ist defekt';

    $this->mock(TeamsTicketContentGenerator::class, function ($mock) use ($rawContent): void {
        $mock->shouldReceive('generate')->once()->andReturn(new GeneratedTeamsTicketContent(
            subject: 'Drucker defekt im 2. OG',
            body: $rawContent,
            generatedByAi: false,
        ));
    });

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(555);
        $mock->shouldReceive('addTagsToTicket');
    });

    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: 'max@example.com',
        azureUserId: 'azure-max',
        displayName: 'Max Mustermann',
        rawContent: $rawContent,
        fallbackSubject: $rawContent,
        fallbackBody: $rawContent,
        sourceLabel: 'Microsoft Teams (Direktnachricht)',
        activity: [],
        conversationRef: [],
    ));

    $body = TicketRequest::query()->firstOrFail()->body;

    expect($body)->toContain($rawContent)
        ->and($body)->not->toContain('Originaltext aus Microsoft Teams:');
});

it('creates a zammad ticket from a teams message for a known user', function (): void {
    $user = User::factory()->create(['active' => true, 'username' => 'max']);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $this->mock(TeamsTicketContentGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(new GeneratedTeamsTicketContent(
            subject: 'Drucker defekt im 2. OG',
            body: 'Der Nutzer meldet einen defekten Drucker im zweiten Obergeschoss.',
        ));
    });

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(555);
        $mock->shouldReceive('addTagsToTicket');
    });

    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: 'max@example.com',
        azureUserId: 'azure-max',
        displayName: 'Max Mustermann',
        rawContent: 'der drucker im 2. og ist defekt',
        fallbackSubject: 'der drucker im 2. og ist defekt',
        fallbackBody: 'der drucker im 2. og ist defekt',
        sourceLabel: 'Microsoft Teams (Direktnachricht)',
        activity: [],
        conversationRef: [],
    ));

    $request = TicketRequest::query()->first();

    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(TicketRequestStatus::Dispatched)
        ->and($request->zammad_ticket_id)->toBe(555)
        ->and($request->subject)->toBe('Drucker defekt im 2. OG')
        ->and($request->body)->toContain('Erstellt über Microsoft Teams');
});

it('resolves the user via the azure object id when no upn is provided', function (): void {
    User::factory()->create([
        'active' => true,
        'username' => 'hwkdo286',
        'socialite_id' => '02bd2b59-d49b-44ce-a709-580a54e1eaf8',
    ]);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();
    $category->update(['zammad_group_id' => 1]);

    $this->mock(TeamsTicketContentGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(new GeneratedTeamsTicketContent(
            subject: 'Erinnerung Wäsche',
            body: 'Der Nutzer möchte an die Wäsche erinnert werden.',
        ));
    });

    $this->mock(ZammadUserResolver::class, function ($mock): void {
        $mock->shouldReceive('resolveCustomerId')->andReturn(99);
    });

    $this->mock(ZammadTicketService::class, function ($mock): void {
        $mock->shouldReceive('createTicket')->once()->andReturn(888);
        $mock->shouldReceive('addTagsToTicket');
    });

    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: null,
        azureUserId: '02bd2b59-d49b-44ce-a709-580a54e1eaf8',
        displayName: 'Alexander Dieckmann',
        rawContent: 'erinnere mich daran die wäsche zu machen',
        fallbackSubject: 'erinnere mich daran die wäsche zu machen',
        fallbackBody: 'erinnere mich daran die wäsche zu machen',
        sourceLabel: 'Microsoft Teams (Direktnachricht)',
        activity: [],
        conversationRef: [],
    ));

    expect(TicketRequest::query()->first()?->zammad_ticket_id)->toBe(888);
});

it('creates no ticket for an unknown user', function (): void {
    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: 'ghost@example.com',
        azureUserId: 'azure-ghost',
        displayName: 'Ghost',
        rawContent: 'Test Inhalt',
        fallbackSubject: 'Test',
        fallbackBody: 'Test Inhalt',
        sourceLabel: 'Microsoft Teams (Direktnachricht)',
        activity: [],
        conversationRef: [],
    ));

    expect(TicketRequest::query()->count())->toBe(0);
});

it('refuses to create tickets for approval categories', function (): void {
    User::factory()->create(['active' => true, 'username' => 'max']);

    config()->set('intranet-app-tickets.teams_bot.default_category_slug', 'marketing');

    TicketCategory::query()->where('slug', 'marketing')->firstOrFail()->update(['zammad_group_id' => 2]);

    dispatch_sync(new CreateTicketFromTeamsMessageJob(
        upn: 'max@example.com',
        azureUserId: 'azure-max',
        displayName: 'Max Mustermann',
        rawContent: 'Bitte einen Flyer erstellen',
        fallbackSubject: 'Flyer',
        fallbackBody: 'Bitte einen Flyer erstellen',
        sourceLabel: 'Microsoft Teams (Direktnachricht)',
        activity: [],
        conversationRef: [],
    ));

    expect(TicketRequest::query()->count())->toBe(0);
});
