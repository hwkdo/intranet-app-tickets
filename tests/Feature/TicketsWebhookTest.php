<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Events\TicketUpdated;
use Hwkdo\IntranetAppTickets\Models\TicketReadState;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Hwkdo\IntranetAppTickets\Services\ZammadWebhookOutcomeRecorder;
use Hwkdo\IntranetAppTickets\Webhooks\Jobs\ZammadWebhookJob;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookClient\Models\WebhookCall;

function ticketsWebhookPayload(string $customerEmail = 'customer@example.com'): array
{
    return [
        'ticket' => [
            'id' => 81,
            'number' => '10081',
            'title' => 'Webhook Test',
            'customer' => [
                'email' => $customerEmail,
            ],
        ],
        'article' => [
            'id' => 104,
            'internal' => false,
            'sender' => 'Agent',
            'body' => 'Agent reply',
        ],
    ];
}

test('zammad webhook job marks ticket unread for matching user', function () {
    Event::fake([TicketUpdated::class]);

    $user = User::factory()->create([
        'email' => 'customer@example.com',
    ]);

    $webhookCall = WebhookCall::create([
        'name' => 'tickets-zammad',
        'url' => 'https://example.com/webhooks/zammad',
        'headers' => [],
        'payload' => ticketsWebhookPayload(),
    ]);

    (new ZammadWebhookJob($webhookCall))->handle(
        app(TicketReadStateService::class),
        app(ZammadWebhookOutcomeRecorder::class),
    );

    expect(TicketReadState::query()->where('user_id', $user->id)->where('has_unread', true)->exists())->toBeTrue();

    Event::assertDispatched(TicketUpdated::class, function (TicketUpdated $event) use ($user) {
        return $event->userId === $user->id
            && $event->ticketId === 81
            && $event->ticketNumber === '10081';
    });
});

test('zammad webhook job marks ticket unread for status change webhooks', function () {
    Event::fake([TicketUpdated::class]);

    $user = User::factory()->create([
        'email' => 'customer@example.com',
    ]);

    $webhookCall = WebhookCall::create([
        'name' => 'tickets-zammad',
        'url' => 'https://example.com/webhooks/zammad',
        'headers' => [],
        'payload' => [
            'ticket' => [
                'id' => 81,
                'number' => '10081',
                'title' => 'Webhook Test',
                'state' => 'closed',
                'customer' => [
                    'email' => 'customer@example.com',
                ],
                'article_ids' => [101, 104],
            ],
            'article' => [],
        ],
    ]);

    (new ZammadWebhookJob($webhookCall))->handle(
        app(TicketReadStateService::class),
        app(ZammadWebhookOutcomeRecorder::class),
    );

    $readState = TicketReadState::query()->where('user_id', $user->id)->first();

    expect($readState)->not->toBeNull()
        ->and($readState->has_unread)->toBeTrue()
        ->and($readState->latest_article_id)->toBe(104)
        ->and($readState->ticket_title)->toBe('Webhook Test · Geschlossen');

    Event::assertDispatched(TicketUpdated::class);
});

test('zammad webhook job ignores unknown customer emails', function () {
    Event::fake([TicketUpdated::class]);

    $webhookCall = WebhookCall::create([
        'name' => 'tickets-zammad',
        'url' => 'https://example.com/webhooks/zammad',
        'headers' => [],
        'payload' => ticketsWebhookPayload('unknown@example.com'),
    ]);

    (new ZammadWebhookJob($webhookCall))->handle(
        app(TicketReadStateService::class),
        app(ZammadWebhookOutcomeRecorder::class),
    );

    expect(TicketReadState::query()->count())->toBe(0);
    Event::assertNotDispatched(TicketUpdated::class);
});
