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

test('it gestuetzte pruefung combines optional date and time fields', function (): void {
    $validation = app(TicketFormValidation::class);

    $filtered = $validation->filterFormData(TicketFormType::ItGestuetztePruefung, [
        'betreff' => 'IT-gestützte Prüfung: Test',
        'pruefungstermin_id' => 1247861,
        'pruefung_id' => 9001,
        'datum' => '2026-08-27',
        'gewerk' => 'Maler',
        'raeume' => '1303, Bildungszentrum HWK Haus I',
        'anzahl_teilnehmer' => 10,
        'ansprechpartner' => 'Susanne Potthoff',
        'verwendete_anwendungen' => 'Moodle',
        'weitere_wichtige_informationen' => 'Namensschilder vorhanden',
        'personalisierte_pruefungsbenutzer' => 'Ja',
        'sperre_pruefungsbenutzer_ab_datum' => '2026-08-28',
        'sperre_pruefungsbenutzer_ab_uhrzeit' => '18:00',
        'loeschung_pruefungsbenutzer_ab_datum' => '',
        'loeschung_pruefungsbenutzer_ab_uhrzeit' => '',
    ]);

    expect($filtered['personalisierte_pruefungsbenutzer'])->toBe('Ja')
        ->and($filtered['sperre_pruefungsbenutzer_ab'])->toBe('2026-08-28 18:00')
        ->and($filtered)->not->toHaveKey('sperre_pruefungsbenutzer_ab_datum')
        ->and($filtered)->not->toHaveKey('sperre_pruefungsbenutzer_ab_uhrzeit')
        ->and($filtered)->not->toHaveKey('loeschung_pruefungsbenutzer_ab')
        ->and($filtered['verwendete_anwendungen'])->toBe('Moodle')
        ->and($filtered['pruefung_id'])->toBe(9001);
});

test('it gestuetzte pruefung drops sperre fields when personalisierte benutzer is nein', function (): void {
    $validation = app(TicketFormValidation::class);

    $filtered = $validation->filterFormData(TicketFormType::ItGestuetztePruefung, [
        'betreff' => 'IT-gestützte Prüfung: Test',
        'pruefungstermin_id' => 1247861,
        'pruefung_id' => 9001,
        'datum' => '2026-08-27',
        'gewerk' => 'Maler',
        'raeume' => '1303',
        'anzahl_teilnehmer' => 10,
        'ansprechpartner' => 'Susanne Potthoff',
        'verwendete_anwendungen' => 'Moodle',
        'personalisierte_pruefungsbenutzer' => 'Nein',
        'sperre_pruefungsbenutzer_ab_datum' => '2026-08-28',
        'sperre_pruefungsbenutzer_ab_uhrzeit' => '18:00',
        'loeschung_pruefungsbenutzer_ab_datum' => '2026-08-29',
        'loeschung_pruefungsbenutzer_ab_uhrzeit' => '12:00',
    ]);

    expect($filtered['personalisierte_pruefungsbenutzer'])->toBe('Nein')
        ->and($filtered)->not->toHaveKey('sperre_pruefungsbenutzer_ab')
        ->and($filtered)->not->toHaveKey('loeschung_pruefungsbenutzer_ab');
});

test('it gestuetzte pruefung requires sperre and loeschung when personalisierte benutzer is ja', function (): void {
    $rules = app(TicketFormValidation::class)->rulesFor(
        TicketFormType::ItGestuetztePruefung,
        personalisiertePruefungsbenutzer: true,
    );

    $validator = validator([
        'betreff' => 'IT-gestützte Prüfung: Test',
        'pruefungstermin_id' => 1,
        'pruefung_id' => 50,
        'datum' => '2026-08-27',
        'gewerk' => 'Maler',
        'raeume' => '1303',
        'anzahl_teilnehmer' => 10,
        'ansprechpartner' => 'Max Mustermann',
        'verwendete_anwendungen' => 'Office',
        'personalisierte_pruefungsbenutzer' => 'Ja',
        'sperre_pruefungsbenutzer_ab_datum' => '2026-08-28',
        'sperre_pruefungsbenutzer_ab_uhrzeit' => null,
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('sperre_pruefungsbenutzer_ab_uhrzeit'))->toBeTrue()
        ->and($validator->errors()->has('loeschung_pruefungsbenutzer_ab_datum'))->toBeTrue();
});

test('it gestuetzte pruefung body uses german labels', function (): void {
    $validation = app(TicketFormValidation::class);
    $builder = app(TicketBodyBuilder::class);

    $formData = $validation->filterFormData(TicketFormType::ItGestuetztePruefung, [
        'betreff' => 'IT-gestützte Prüfung: Test',
        'pruefungstermin_id' => 1247861,
        'pruefung_id' => 9001,
        'datum' => '2026-08-27',
        'gewerk' => 'Maler',
        'raeume' => '1303',
        'anzahl_teilnehmer' => 10,
        'ansprechpartner' => 'Susanne Potthoff',
        'verwendete_anwendungen' => 'Moodle',
        'personalisierte_pruefungsbenutzer' => 'Ja',
        'sperre_pruefungsbenutzer_ab_datum' => '2026-08-28',
        'sperre_pruefungsbenutzer_ab_uhrzeit' => '18:00',
        'loeschung_pruefungsbenutzer_ab_datum' => '2026-08-29',
        'loeschung_pruefungsbenutzer_ab_uhrzeit' => '12:00',
    ]);

    $body = $builder->build('', $formData);

    expect($body)
        ->toContain('PrüfungsterminID: 1247861')
        ->toContain('PrüfungID: 9001')
        ->toContain('Gewerk (Ordnung): Maler')
        ->toContain('Verwendete Anwendungen: Moodle')
        ->toContain('Personalisierte Prüfungsbenutzer: Ja')
        ->toContain('Sperre Prüfungsbenutzer ab: 2026-08-28 18:00')
        ->toContain('Löschung Prüfungsbenutzer ab: 2026-08-29 12:00')
        ->toContain('Ansprechpartner: Susanne Potthoff')
        ->not->toContain('Die Prüfung findet in');
});

test('it gestuetzte pruefung multi body lists rooms then shared fields', function (): void {
    $validation = app(TicketFormValidation::class);
    $builder = app(TicketBodyBuilder::class);

    $formData = $validation->filterFormData(TicketFormType::ItGestuetztePruefung, [
        'betreff' => 'IT-gestützte Prüfung: Test (2 Räume)',
        'pruefung_id' => 50,
        'pruefungstermine' => [
            [
                'pruefungstermin_id' => 1001,
                'raeume' => '1301',
                'anzahl_teilnehmer' => 8,
            ],
            [
                'pruefungstermin_id' => 1002,
                'raeume' => '1302',
                'anzahl_teilnehmer' => 12,
            ],
        ],
        'datum' => '2026-08-27',
        'gewerk' => 'Maler',
        'ansprechpartner' => 'Susanne Potthoff',
        'verwendete_anwendungen' => 'Moodle',
        'personalisierte_pruefungsbenutzer' => 'Nein',
    ]);

    $body = $builder->build('', $formData);

    expect($formData)->toHaveKey('pruefungstermine')
        ->and($formData)->not->toHaveKey('pruefungstermin_id')
        ->and($formData['pruefung_id'])->toBe(50)
        ->and($formData['personalisierte_pruefungsbenutzer'])->toBe('Nein')
        ->and($body)->toStartWith('Die Prüfung findet in 2 Räumen statt.')
        ->and($body)->toContain('PrüfungID: 50')
        ->and($body)->toContain("PrüfungsterminID: 1001\n\nRaum: 1301\n\nAnzahl Teilnehmer: 8")
        ->and($body)->toContain("PrüfungsterminID: 1002\n\nRaum: 1302\n\nAnzahl Teilnehmer: 12")
        ->and($body)->toContain('Datum: 2026-08-27')
        ->and($body)->toContain('Gewerk (Ordnung): Maler')
        ->and($body)->toContain('Personalisierte Prüfungsbenutzer: Nein')
        ->and($body)->not->toContain('Räume:');
});
