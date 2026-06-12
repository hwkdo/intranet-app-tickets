<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Enums\TicketFormType;
use Hwkdo\IntranetAppTickets\Services\TicketBodyBuilder;
use Hwkdo\IntranetAppTickets\Services\TicketFormValidation;

test('it support form data excludes fields from other categories', function (): void {
    $validation = app(TicketFormValidation::class);

    $filtered = $validation->filterFormData(TicketFormType::ItSupport, [
        'betreff' => 'Drucker defekt',
        'inhalt' => '<p>Test</p>',
        'on_behalf_of_user_id' => 1,
        'meisterbrief' => false,
        'Druckanzahl' => null,
        'farbe' => '',
    ]);

    expect($filtered)->toBe([
        'betreff' => 'Drucker defekt',
        'inhalt' => '<p>Test</p>',
        'on_behalf_of_user_id' => 1,
    ]);
});

test('it support ticket body does not append druck fields', function (): void {
    $validation = app(TicketFormValidation::class);
    $builder = app(TicketBodyBuilder::class);

    $formData = $validation->filterFormData(TicketFormType::ItSupport, [
        'betreff' => 'Drucker defekt',
        'inhalt' => 'Test',
        'on_behalf_of_user_id' => 1,
        'meisterbrief' => false,
    ]);

    $body = $builder->build('Test', $formData);

    expect($body)->toBe('Test')
        ->and($body)->not->toContain('Meisterbrief')
        ->and($body)->not->toContain('<p>');
});

test('ticket body appends metadata as plain text', function (): void {
    $requester = User::factory()->make(['vorname' => 'Alexander', 'nachname' => 'Dieckmann']);
    $onBehalfOf = User::factory()->make(['vorname' => 'Maria', 'nachname' => 'Muster']);

    $body = app(TicketBodyBuilder::class)->build(
        baseContent: 'test',
        formData: [],
        requester: $requester,
        onBehalfOf: $onBehalfOf,
    );

    expect($body)->toBe("test\n\nErstellt von: Alexander Dieckmann\n\nErstellt für: Maria Muster")
        ->and($body)->not->toContain('<p>');
});

test('druckauftrag keeps meisterbrief flag in form data', function (): void {
    $validation = app(TicketFormValidation::class);

    $filtered = $validation->filterFormData(TicketFormType::Druckauftrag, [
        'meisterbrief' => false,
        'inhalt' => 'Flyer',
        'datum' => '2026-06-20',
        'betreff' => 'Druckauftrag',
    ]);

    expect($filtered)->toHaveKey('meisterbrief')
        ->and($filtered['meisterbrief'])->toBeFalse();
});
