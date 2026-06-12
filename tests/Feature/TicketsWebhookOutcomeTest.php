<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Enums\ZammadWebhookOutcomeStatus;
use Hwkdo\IntranetAppTickets\Events\TicketUpdated;
use Hwkdo\IntranetAppTickets\Events\ZammadWebhookActivity;
use Hwkdo\IntranetAppTickets\Models\ZammadWebhookOutcome;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Hwkdo\IntranetAppTickets\Services\ZammadWebhookOutcomeRecorder;
use Hwkdo\IntranetAppTickets\Webhooks\Jobs\ZammadWebhookJob;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\WebhookClient\Models\WebhookCall;

test('zammad webhook job records processed outcome', function () {
    Event::fake([TicketUpdated::class, ZammadWebhookActivity::class]);

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

    $outcome = ZammadWebhookOutcome::query()->where('webhook_call_id', $webhookCall->id)->first();

    expect($outcome)->not->toBeNull()
        ->and($outcome->status)->toBe(ZammadWebhookOutcomeStatus::Processed)
        ->and($outcome->user_id)->toBe($user->id)
        ->and($outcome->message)->toContain('customer@example.com');

    Event::assertDispatched(ZammadWebhookActivity::class, function (ZammadWebhookActivity $event) use ($webhookCall) {
        return $event->webhookCallId === $webhookCall->id
            && $event->phase === 'processed'
            && $event->status === ZammadWebhookOutcomeStatus::Processed->value;
    });
});

test('zammad webhook job records skipped outcome for internal articles', function () {
    Event::fake([TicketUpdated::class, ZammadWebhookActivity::class]);

    $payload = ticketsWebhookPayload();
    $payload['article']['internal'] = true;

    $webhookCall = WebhookCall::create([
        'name' => 'tickets-zammad',
        'url' => 'https://example.com/webhooks/zammad',
        'headers' => [],
        'payload' => $payload,
    ]);

    (new ZammadWebhookJob($webhookCall))->handle(
        app(TicketReadStateService::class),
        app(ZammadWebhookOutcomeRecorder::class),
    );

    $outcome = ZammadWebhookOutcome::query()->where('webhook_call_id', $webhookCall->id)->first();

    expect($outcome)->not->toBeNull()
        ->and($outcome->status)->toBe(ZammadWebhookOutcomeStatus::SkippedInternal);

    Event::assertNotDispatched(TicketUpdated::class);
});

test('zammad webhook job records skipped outcome for unknown customer emails', function () {
    Event::fake([TicketUpdated::class, ZammadWebhookActivity::class]);

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

    $outcome = ZammadWebhookOutcome::query()->where('webhook_call_id', $webhookCall->id)->first();

    expect($outcome)->not->toBeNull()
        ->and($outcome->status)->toBe(ZammadWebhookOutcomeStatus::SkippedNoUser);

    Event::assertNotDispatched(TicketUpdated::class);
});

test('stored zammad webhook broadcasts received activity', function () {
    Event::fake([ZammadWebhookActivity::class]);

    $webhookCall = WebhookCall::create([
        'name' => 'tickets-zammad',
        'url' => 'https://example.com/webhooks/zammad',
        'headers' => [],
        'payload' => ticketsWebhookPayload(),
    ]);

    Event::assertDispatched(ZammadWebhookActivity::class, function (ZammadWebhookActivity $event) use ($webhookCall) {
        return $event->webhookCallId === $webhookCall->id
            && $event->phase === 'received';
    });
});

test('tickets zammad webhooks channel requires manage permission', function () {
    $user = User::factory()->create();
    $manager = User::factory()->create();

    Permission::findOrCreate('manage-app-tickets', 'web');
    $manager->givePermissionTo('manage-app-tickets');

    $callback = app(BroadcastManager::class)->driver()->getChannels()['tickets-zammad-webhooks'];

    expect($callback($user))->toBeFalse()
        ->and($callback($manager))->toBeTrue();
});
