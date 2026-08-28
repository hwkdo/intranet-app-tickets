<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBase\Services\ManualCatalog;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('see-app-tickets', 'web');
});

test('tickets manual page renders for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-tickets');

    $this->actingAs($user)
        ->get(route('apps.tickets.manual'))
        ->assertOk()
        ->assertSee('Bedienungsanleitung')
        ->assertSee('1. Einstieg')
        ->assertSeeLivewire('intranet-app-base::manual-show');
});

test('tickets manual page is forbidden without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('apps.tickets.manual'))
        ->assertForbidden();
});

test('tickets manual asset is served for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-tickets');

    $this->actingAs($user)
        ->get(route('intranet.manuals.asset', [
            'manualKey' => 'tickets.onboarding',
            'filename' => '01-welcome.jpg',
        ]))
        ->assertOk();
});

test('tickets manual asset returns not found for unknown file', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-tickets');

    $this->actingAs($user)
        ->get(route('intranet.manuals.asset', [
            'manualKey' => 'tickets.onboarding',
            'filename' => '../secret.jpg',
        ]))
        ->assertNotFound();
});

test('primary manual resolves by app identifier', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-tickets');

    $primary = app(ManualCatalog::class)->primaryForApp($user, 'tickets');

    expect($primary)->not->toBeNull()
        ->and($primary->key)->toBe('tickets.onboarding');
});
