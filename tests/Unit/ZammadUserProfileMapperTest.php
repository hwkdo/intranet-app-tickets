<?php

declare(strict_types=1);

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppTickets\Support\ZammadUserProfileMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('toCreatePayload maps intranet user fields for zammad', function (): void {
    $standort = Standort::query()->create([
        'name' => 'Dortmund',
        'extension' => 'DO',
        'strasse' => 'Musterstraße 1',
        'ort' => 'Dortmund',
        'plz' => '44135',
    ]);

    $user = User::factory()->create([
        'active' => true,
        'vorname' => 'Hans',
        'nachname' => 'Müller',
        'email' => 'hans.mueller@example.com',
        'username' => 'hmueller',
        'telefon' => '0231 123456',
        'raum' => 'A101',
        'standort_id' => $standort->id,
    ]);

    $payload = (new ZammadUserProfileMapper)->toCreatePayload($user, 9);

    expect($payload)->toMatchArray([
        'firstname' => 'Hans',
        'lastname' => 'Müller',
        'email' => 'hans.mueller@example.com',
        'login' => 'hans.mueller@example.com',
        'phone' => '0231 123456',
        'department' => 'Dortmund',
        'role_ids' => [9],
    ])
        ->and($payload['note'])->toContain('Raum: A101')
        ->and($payload['note'])->toContain('Intranet-Benutzer: hmueller');
});

test('resolveEmail falls back to upn when email is empty', function (): void {
    $user = User::factory()->make([
        'email' => '',
        'username' => 'jdoe',
    ]);

    $email = (new ZammadUserProfileMapper)->resolveEmail($user);

    expect($email)->toBe('jdoe@'.config('ms-graph-laravel.default_suffix'));
});
