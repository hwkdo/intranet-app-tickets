<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Support\ZammadWebhookPayload;
use Hwkdo\IntranetAppTickets\Webhooks\Jobs\ZammadWebhookJob;
use Hwkdo\IntranetAppTickets\Webhooks\SignatureValidators\ZammadSignatureValidator;
use Illuminate\Http\Request;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

it('accepts public agent articles', function () {
    $payload = ZammadWebhookPayload::from([
        'article' => [
            'id' => 1,
            'internal' => false,
            'sender' => 'Agent',
        ],
    ]);

    expect($payload->isAgentPublicArticle())->toBeTrue();
});

it('treats missing internal flag as public like legacy', function () {
    $payload = ZammadWebhookPayload::from([
        'article' => [
            'id' => 1,
            'sender' => 'Agent',
        ],
    ]);

    expect($payload->isAgentPublicArticle())->toBeTrue();
});

it('accepts internal zero as public', function () {
    $payload = ZammadWebhookPayload::from([
        'article' => [
            'id' => 1,
            'internal' => 0,
            'sender' => 'Agent',
        ],
    ]);

    expect($payload->isAgentPublicArticle())->toBeTrue();
});

it('rejects internal agent articles', function () {
    $payload = ZammadWebhookPayload::from([
        'article' => [
            'id' => 1,
            'internal' => true,
            'sender' => 'Agent',
        ],
    ]);

    expect($payload->isAgentPublicArticle())->toBeFalse();
});

it('rejects public customer articles', function () {
    $payload = ZammadWebhookPayload::from([
        'article' => [
            'id' => 1,
            'internal' => false,
            'sender' => 'Customer',
        ],
    ]);

    expect($payload->isAgentPublicArticle())->toBeFalse();
});

it('treats empty article array as ticket status webhook', function () {
    $payload = ZammadWebhookPayload::from([
        'ticket' => [
            'id' => 48590,
            'number' => '3148509',
            'title' => 'Test',
            'state' => 'closed',
            'customer' => ['email' => 'customer@example.com'],
            'article_ids' => [191687, 191692],
        ],
        'article' => [],
    ]);

    expect($payload->hasArticle())->toBeFalse()
        ->and($payload->isTicketStatusUpdate())->toBeTrue()
        ->and($payload->latestTicketArticleId())->toBe(191692)
        ->and($payload->ticketStateLabel())->toBe('Geschlossen');
});

it('validates zammad hmac sha1 signatures with sha1 prefix', function () {
    $secret = 'test-webhook-secret';
    $body = '{"ticket":{"id":1}}';
    $signature = 'sha1='.hash_hmac('sha1', $body, $secret);

    $request = Request::create('/webhooks/zammad', 'POST', [], [], [], [], $body);
    $request->headers->set('X-Hub-Signature', $signature);

    $config = new WebhookConfig([
        'name' => 'tickets-zammad',
        'signing_secret' => $secret,
        'signature_header_name' => 'X-Hub-Signature',
        'signature_validator' => ZammadSignatureValidator::class,
        'webhook_profile' => ProcessEverythingWebhookProfile::class,
        'webhook_response' => DefaultRespondsTo::class,
        'webhook_model' => WebhookCall::class,
        'process_webhook_job' => ZammadWebhookJob::class,
    ]);

    $validator = new ZammadSignatureValidator;

    expect($validator->isValid($request, $config))->toBeTrue();
});

it('rejects invalid signatures', function () {
    $request = Request::create('/webhooks/zammad', 'POST', [], [], [], [], '{"ticket":{"id":1}}');
    $request->headers->set('X-Hub-Signature', 'invalid');

    $config = new WebhookConfig([
        'name' => 'tickets-zammad',
        'signing_secret' => 'test-webhook-secret',
        'signature_header_name' => 'X-Hub-Signature',
        'signature_validator' => ZammadSignatureValidator::class,
        'webhook_profile' => ProcessEverythingWebhookProfile::class,
        'webhook_response' => DefaultRespondsTo::class,
        'webhook_model' => WebhookCall::class,
        'process_webhook_job' => ZammadWebhookJob::class,
    ]);

    $validator = new ZammadSignatureValidator;

    expect($validator->isValid($request, $config))->toBeFalse();
});
