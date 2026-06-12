<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Services\ZammadClientFactory;
use Hwkdo\IntranetAppTickets\Services\ZammadRoleService;
use Illuminate\Support\Facades\Cache;
use ZammadAPIClient\Client;
use ZammadAPIClient\Client\Response;

beforeEach(function (): void {
    Cache::flush();
});

test('listRoles maps zammad roles from api response', function (): void {
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('hasError')->andReturn(false);
    $response->shouldReceive('getData')->andReturn([
        ['id' => 3, 'name' => 'Customer', 'active' => true],
        ['id' => 2, 'name' => 'Agent', 'active' => true],
        ['id' => 9, 'name' => 'Intranet-User', 'active' => true],
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')->once()->with('roles')->andReturn($response);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($client);

    $roles = (new ZammadRoleService($factory))->listRoles();

    expect($roles)->toHaveCount(3)
        ->and($roles->first()['name'])->toBe('Agent');
});

test('getRole and updateGroupPermissions merge group ids for zammad put', function (): void {
    $getResponse = Mockery::mock(Response::class);
    $getResponse->shouldReceive('hasError')->andReturn(false);
    $getResponse->shouldReceive('getData')->andReturn([
        'id' => 9,
        'name' => 'Intranet-User',
        'active' => true,
        'note' => 'Intranet',
        'default_at_signup' => false,
        'permission_ids' => [57],
        'group_ids' => [
            '1' => ['full'],
            '4' => ['read'],
        ],
    ]);

    $putResponse = Mockery::mock(Response::class);
    $putResponse->shouldReceive('hasError')->andReturn(false);
    $putResponse->shouldReceive('getData')->andReturn(['id' => 9]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')->once()->with('roles/9')->andReturn($getResponse);
    $client->shouldReceive('put')
        ->once()
        ->with('roles/9', Mockery::on(function (array $payload): bool {
            return ($payload['name'] ?? null) === 'Intranet-User'
                && ($payload['group_ids']['1'] ?? null) === 'full'
                && ($payload['group_ids']['4'] ?? null) === 'create'
                && ($payload['group_ids']['2'] ?? null) === 'read'
                && ! array_key_exists('3', $payload['group_ids'] ?? []);
        }))
        ->andReturn($putResponse);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->twice()->andReturn($client);

    $service = new ZammadRoleService($factory);

    $service->updateGroupPermissions(9, [
        '4' => 'create',
        '2' => 'read',
        '3' => '',
    ]);

    expect(ZammadRoleService::normalizeAccessValue(['full']))->toBe('full');
});

test('parseGroupPermissionsFromRole normalizes array access values', function (): void {
    $factory = Mockery::mock(ZammadClientFactory::class);
    $service = new ZammadRoleService($factory);

    $permissions = $service->parseGroupPermissionsFromRole([
        'group_ids' => [
            4 => ['create'],
            '2' => 'read',
        ],
    ]);

    expect($permissions)->toBe([
        '4' => 'create',
        '2' => 'read',
    ]);
});
