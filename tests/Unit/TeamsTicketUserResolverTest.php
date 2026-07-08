<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('intranet-app-tickets.user_model', User::class);
    $this->resolver = app(TeamsTicketUserResolver::class);
});

it('resolves quoted sender by azure object id', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'socialite_id' => '11111111-2222-3333-4444-555555555555',
        'vorname' => 'Markus',
        'nachname' => 'Lubritz',
    ]);

    expect($this->resolver->resolveQuotedSender('11111111-2222-3333-4444-555555555555', 'Lubritz, Markus'))
        ->not->toBeNull()
        ->and($this->resolver->resolveQuotedSender('11111111-2222-3333-4444-555555555555', 'Lubritz, Markus')?->getAuthIdentifier())
        ->toBe($user->id);
});

it('resolves quoted sender by ldap style display name when azure id is missing', function (): void {
    User::factory()->create([
        'active' => true,
        'vorname' => 'Markus',
        'nachname' => 'Lubritz',
        'username' => 'hwkdo123',
    ]);

    $resolved = $this->resolver->resolveQuotedSender(null, 'Lubritz, Markus');

    expect($resolved)->not->toBeNull()
        ->and($resolved?->nachname)->toBe('Lubritz')
        ->and($resolved?->vorname)->toBe('Markus');
});

it('resolves quoted sender by first and last name format', function (): void {
    User::factory()->create([
        'active' => true,
        'vorname' => 'Markus',
        'nachname' => 'Lubritz',
    ]);

    expect($this->resolver->resolveQuotedSender(null, 'Markus Lubritz'))->not->toBeNull();
});

it('does not guess when multiple users share the same name', function (): void {
    User::factory()->create(['active' => true, 'vorname' => 'Markus', 'nachname' => 'Lubritz']);
    User::factory()->create(['active' => true, 'vorname' => 'Markus', 'nachname' => 'Lubritz']);

    expect($this->resolver->resolveQuotedSender(null, 'Lubritz, Markus'))->toBeNull();
});

it('ignores inactive users when resolving quoted sender by display name', function (): void {
    User::factory()->create([
        'active' => false,
        'vorname' => 'Markus',
        'nachname' => 'Lubritz',
    ]);

    expect($this->resolver->resolveQuotedSender(null, 'Lubritz, Markus'))->toBeNull();
});
