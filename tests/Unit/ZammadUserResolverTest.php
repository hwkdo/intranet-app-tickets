<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Models\IntranetAppTicketsSettings;
use Hwkdo\IntranetAppTickets\Models\ZammadUserMapping;
use Hwkdo\IntranetAppTickets\Services\TicketsAppSettingsStore;
use Hwkdo\IntranetAppTickets\Services\ZammadClientFactory;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Hwkdo\IntranetAppTickets\Support\ZammadUserProfileMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\AbstractResource;
use ZammadAPIClient\ResourceType;

uses(RefreshDatabase::class);

test('resolveCustomerId creates zammad user when search finds none', function (): void {
    IntranetAppTicketsSettings::query()->create([
        'version' => 1,
        'settings' => [
            'maxTicketsPerPage' => 100,
            'zammadIntranetUserRoleId' => 9,
        ],
    ]);

    $user = User::factory()->create([
        'active' => true,
        'vorname' => 'Neu',
        'nachname' => 'Nutzer',
        'email' => 'neu.nutzer@example.com',
    ]);

    $searchResource = Mockery::mock(AbstractResource::class);
    $searchResource->shouldReceive('search')->once()->with('neu.nutzer@example.com')->andReturn([]);

    $createdResource = Mockery::mock(AbstractResource::class);
    $createdResource->shouldReceive('setValues')->once()->with(Mockery::on(function (array $payload): bool {
        return ($payload['email'] ?? null) === 'neu.nutzer@example.com'
            && ($payload['role_ids'] ?? null) === [9];
    }));
    $createdResource->shouldReceive('save')->once();
    $createdResource->shouldReceive('hasError')->andReturn(false);
    $createdResource->shouldReceive('getId')->andReturn(321);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('resource')
        ->twice()
        ->with(ResourceType::USER)
        ->andReturn($searchResource, $createdResource);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->twice()->andReturn($client);

    $resolver = new ZammadUserResolver(
        $factory,
        new ZammadUserProfileMapper,
        app(TicketsAppSettingsStore::class),
    );

    expect($resolver->resolveCustomerId($user))->toBe(321);

    expect(ZammadUserMapping::query()->where('user_id', $user->id)->value('zammad_customer_id'))->toBe(321);
});

test('resolveCustomerId returns existing mapping without api calls', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    ZammadUserMapping::query()->create([
        'user_id' => $user->id,
        'zammad_customer_id' => 55,
        'zammad_email' => 'existing@example.com',
        'resolved_at' => now(),
    ]);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldNotReceive('make');

    $resolver = new ZammadUserResolver(
        $factory,
        new ZammadUserProfileMapper,
        app(TicketsAppSettingsStore::class),
    );

    expect($resolver->resolveCustomerId($user))->toBe(55);
});

test('isStaleZammadUserError detects missing zammad user responses', function (): void {
    $resolver = new ZammadUserResolver(
        Mockery::mock(ZammadClientFactory::class),
        new ZammadUserProfileMapper,
        app(TicketsAppSettingsStore::class),
    );

    expect($resolver->isStaleZammadUserError("No such user '1221'"))->toBeTrue()
        ->and($resolver->isStaleZammadUserError('Not authorized'))->toBeFalse()
        ->and($resolver->isStaleZammadUserError(null))->toBeFalse();
});

test('forgetMappingIfStaleUserError removes mapping only for stale user errors', function (): void {
    $user = User::factory()->create(['email' => 'emily.lenz@hwk-do.de']);

    ZammadUserMapping::query()->create([
        'user_id' => $user->id,
        'zammad_customer_id' => 1221,
        'zammad_email' => 'emily.lenz@hwk-do.de',
        'resolved_at' => now(),
    ]);

    $resolver = new ZammadUserResolver(
        Mockery::mock(ZammadClientFactory::class),
        new ZammadUserProfileMapper,
        app(TicketsAppSettingsStore::class),
    );

    expect($resolver->forgetMappingIfStaleUserError($user, "No such user '1221'"))->toBeTrue()
        ->and(ZammadUserMapping::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($resolver->forgetMappingIfStaleUserError($user, 'Not authorized'))->toBeFalse();
});

test('provisionCustomer creates zammad user and stores mapping', function (): void {
    IntranetAppTicketsSettings::query()->create([
        'version' => 1,
        'settings' => [
            'maxTicketsPerPage' => 100,
            'zammadIntranetUserRoleId' => 9,
        ],
    ]);

    $user = User::factory()->create([
        'active' => true,
        'vorname' => 'Manuell',
        'nachname' => 'Angelegt',
        'email' => 'manuell@example.com',
    ]);

    $searchResource = Mockery::mock(AbstractResource::class);
    $searchResource->shouldReceive('search')->once()->andReturn([]);

    $createdResource = Mockery::mock(AbstractResource::class);
    $createdResource->shouldReceive('setValues')->once();
    $createdResource->shouldReceive('save')->once();
    $createdResource->shouldReceive('hasError')->andReturn(false);
    $createdResource->shouldReceive('getId')->andReturn(654);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('resource')
        ->twice()
        ->with(ResourceType::USER)
        ->andReturn($searchResource, $createdResource);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->twice()->andReturn($client);

    $resolver = new ZammadUserResolver(
        $factory,
        new ZammadUserProfileMapper,
        app(TicketsAppSettingsStore::class),
    );

    expect($resolver->provisionCustomer($user))->toBe(654);
    expect($resolver->provisionCustomer($user))->toBe(654);
});
