<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Services\ZammadClientFactory;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Hwkdo\IntranetAppTickets\Services\ZammadUserRoleService;
use Illuminate\Support\Facades\Cache;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\AbstractResource;
use ZammadAPIClient\ResourceType;

beforeEach(function (): void {
    Cache::flush();
});

test('emailHasRole returns true when role is assigned in zammad', function (): void {
    $service = new ZammadUserRoleService(
        Mockery::mock(ZammadClientFactory::class),
        Mockery::mock(ZammadUserResolver::class),
    );

    $map = collect([
        'user@example.com' => [2, 9],
    ]);

    expect($service->emailHasRole('user@example.com', 9, $map))->toBeTrue()
        ->and($service->emailHasRole('user@example.com', 3, $map))->toBeFalse()
        ->and($service->emailHasRole(null, 9, $map))->toBeFalse();
});

test('assignRoleToUser merges role into existing zammad role ids', function (): void {
    $user = User::factory()->make(['id' => 5, 'email' => 'user@example.com']);

    $resolver = Mockery::mock(ZammadUserResolver::class);
    $resolver->shouldReceive('resolveCustomerId')->once()->with($user)->andReturn(42);

    $userResource = Mockery::mock(AbstractResource::class);
    $userResource->shouldReceive('hasError')->andReturn(false);
    $userResource->shouldReceive('getId')->andReturn(42);
    $userResource->shouldReceive('getValues')->andReturn(['role_ids' => [2]]);
    $userResource->shouldReceive('setValues')
        ->once()
        ->with(['role_ids' => [2, 9]]);
    $userResource->shouldReceive('save')->once();
    $userResource->shouldReceive('hasError')->andReturn(false);

    $userRepository = Mockery::mock(AbstractResource::class);
    $userRepository->shouldReceive('get')->once()->with(42)->andReturn($userResource);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('resource')
        ->once()
        ->with(ResourceType::USER)
        ->andReturn($userRepository);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($client);

    app()->instance(ZammadClientFactory::class, $factory);
    app()->instance(ZammadUserResolver::class, $resolver);

    (new ZammadUserRoleService($factory, $resolver))->assignRoleToUser($user, 9);
});
