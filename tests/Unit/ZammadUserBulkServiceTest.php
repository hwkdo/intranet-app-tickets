<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Services\ZammadUserBulkService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Hwkdo\IntranetAppTickets\Services\ZammadUserRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

test('createAllMissingUsers provisions only users missing in zammad', function (): void {
    $existing = User::factory()->create([
        'active' => true,
        'email' => 'existing@example.com',
        'vorname' => 'Existiert',
        'nachname' => 'Bereits',
    ]);

    $missing = User::factory()->create([
        'active' => true,
        'email' => 'missing@example.com',
        'vorname' => 'Fehlt',
        'nachname' => 'Noch',
    ]);

    $roleService = Mockery::mock(ZammadUserRoleService::class);
    $roleService->shouldReceive('getEmailToRoleIdsMap')
        ->andReturn(collect(['existing@example.com' => [9]]), collect([
            'existing@example.com' => [9],
            'missing@example.com' => [9],
        ]));

    $resolver = Mockery::mock(ZammadUserResolver::class);
    $resolver->shouldReceive('provisionCustomer')
        ->once()
        ->with(Mockery::on(fn (User $user): bool => $user->is($missing)))
        ->andReturn(123);

    $service = new ZammadUserBulkService(
        $resolver,
        $roleService,
        app(\Hwkdo\IntranetAppTickets\Support\ZammadUserProfileMapper::class),
        app(\Hwkdo\IntranetAppTickets\Support\IntranetUserSearchFilter::class),
    );

    $result = $service->createAllMissingUsers();

    expect($result->succeeded)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and($result->failed)->toBe(0);
});

test('assignIntranetRoleToAllMissing assigns role only to zammad users without it', function (): void {
    $needsRole = User::factory()->create([
        'active' => true,
        'email' => 'needs-role@example.com',
        'vorname' => 'Braucht',
        'nachname' => 'Rolle',
    ]);

    User::factory()->create([
        'active' => true,
        'email' => 'has-role@example.com',
        'vorname' => 'Hat',
        'nachname' => 'Rolle',
    ]);

    $roleMap = collect([
        'needs-role@example.com' => [2],
        'has-role@example.com' => [9, 2],
    ]);

    $roleService = Mockery::mock(ZammadUserRoleService::class);
    $roleService->shouldReceive('getEmailToRoleIdsMap')->andReturn($roleMap, $roleMap);
    $roleService->shouldReceive('emailHasRole')
        ->andReturnUsing(function (?string $email, int $roleId, ?Collection $map) use ($roleMap): bool {
            $roleIds = $roleMap->get(mb_strtolower((string) $email), []);

            return in_array($roleId, $roleIds, true);
        });
    $roleService->shouldReceive('assignRoleToUser')
        ->once()
        ->with(Mockery::on(fn (User $user): bool => $user->is($needsRole)), 9);

    $service = new ZammadUserBulkService(
        Mockery::mock(ZammadUserResolver::class),
        $roleService,
        app(\Hwkdo\IntranetAppTickets\Support\ZammadUserProfileMapper::class),
        app(\Hwkdo\IntranetAppTickets\Support\IntranetUserSearchFilter::class),
    );

    $result = $service->assignIntranetRoleToAllMissing(9);

    expect($result->succeeded)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and($result->failed)->toBe(0);
});
