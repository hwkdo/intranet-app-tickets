<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Services\ZammadClientFactory;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\AbstractResource;
use ZammadAPIClient\ResourceType;

test('createTicket acts on behalf of the mapped zammad customer', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $resolver = Mockery::mock(ZammadUserResolver::class);
    $resolver->shouldReceive('resolveCustomerId')
        ->once()
        ->with($user)
        ->andReturn(42);

    $ticketResource = Mockery::mock(AbstractResource::class);
    $ticketResource->shouldReceive('setValues')
        ->once()
        ->with(Mockery::on(function (array $values): bool {
            return ($values['customer_id'] ?? null) === 42
                && ($values['article']['created_by_id'] ?? null) === 42
                && ($values['article']['content_type'] ?? null) === 'text/plain';
        }));
    $ticketResource->shouldReceive('save')->once();
    $ticketResource->shouldReceive('hasError')->andReturn(false);
    $ticketResource->shouldReceive('getId')->andReturn(123);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setOnBehalfOfUser')
        ->once()
        ->with('42');
    $client->shouldReceive('resource')
        ->once()
        ->with(ResourceType::TICKET)
        ->andReturn($ticketResource);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($client);

    $service = new ZammadTicketService($factory, $resolver, new \Hwkdo\IntranetAppTickets\Services\ZammadArticleBodyRenderer);

    expect($service->createTicket($user, 1, 'Betreff', '<p>Inhalt</p>'))->toBe(123);
});

test('createTicket retries once after stale zammad user mapping error', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $resolver = Mockery::mock(ZammadUserResolver::class);
    $resolver->shouldReceive('resolveCustomerId')
        ->twice()
        ->with($user)
        ->andReturn(1221, 900);
    $resolver->shouldReceive('forgetMappingIfStaleUserError')
        ->once()
        ->with($user, "No such user '1221'")
        ->andReturn(true);

    $failedTicket = Mockery::mock(AbstractResource::class);
    $failedTicket->shouldReceive('setValues')->once();
    $failedTicket->shouldReceive('save')->once();
    $failedTicket->shouldReceive('hasError')->andReturn(true);
    $failedTicket->shouldReceive('getError')->andReturn("No such user '1221'");

    $successTicket = Mockery::mock(AbstractResource::class);
    $successTicket->shouldReceive('setValues')->once();
    $successTicket->shouldReceive('save')->once();
    $successTicket->shouldReceive('hasError')->andReturn(false);
    $successTicket->shouldReceive('getId')->andReturn(456);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setOnBehalfOfUser')->once()->with('1221');
    $client->shouldReceive('setOnBehalfOfUser')->once()->with('900');
    $client->shouldReceive('resource')
        ->twice()
        ->with(ResourceType::TICKET)
        ->andReturn($failedTicket, $successTicket);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->twice()->andReturn($client);

    $service = new ZammadTicketService($factory, $resolver, new \Hwkdo\IntranetAppTickets\Services\ZammadArticleBodyRenderer);

    expect($service->createTicket($user, 1, 'Betreff', 'Inhalt'))->toBe(456);
});
