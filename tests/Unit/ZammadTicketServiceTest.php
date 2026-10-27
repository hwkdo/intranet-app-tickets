<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Services\ZammadArticleBodyRenderer;
use Hwkdo\IntranetAppTickets\Services\ZammadClientFactory;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Hwkdo\IntranetAppTickets\Support\ZammadTicketSearchQueryBuilder;
use RuntimeException;
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

    $service = new ZammadTicketService(
        $factory,
        $resolver,
        new ZammadArticleBodyRenderer,
        new ZammadTicketSearchQueryBuilder,
    );

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

    $service = new ZammadTicketService(
        $factory,
        $resolver,
        new ZammadArticleBodyRenderer,
        new ZammadTicketSearchQueryBuilder,
    );

    expect($service->createTicket($user, 1, 'Betreff', 'Inhalt'))->toBe(456);
});

test('replyToTicket writes a public customer article on behalf of the mapped user', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $resolver = Mockery::mock(ZammadUserResolver::class);
    $resolver->shouldReceive('resolveCustomerId')
        ->twice()
        ->with($user)
        ->andReturn(42);

    $ticketResource = Mockery::mock(AbstractResource::class);
    $ticketResource->shouldReceive('get')->once()->with(315)->andReturnSelf();
    $ticketResource->shouldReceive('hasError')->once()->andReturn(false);
    $ticketResource->shouldReceive('getId')->once()->andReturn(315);
    $ticketResource->shouldReceive('getValues')->once()->andReturn([
        'id' => 315,
        'customer_id' => 42,
    ]);

    $articleResource = Mockery::mock(AbstractResource::class);
    $articleResource->shouldReceive('setValues')
        ->once()
        ->with(Mockery::on(function (array $values): bool {
            return ($values['ticket_id'] ?? null) === 315
                && ($values['body'] ?? null) === 'Ich freue mich auf den Termin.'
                && ($values['type'] ?? null) === 'web'
                && ($values['content_type'] ?? null) === 'text/plain'
                && ($values['internal'] ?? null) === false
                && ($values['created_by_id'] ?? null) === 42;
        }));
    $articleResource->shouldReceive('save')->once();
    $articleResource->shouldReceive('hasError')->once()->andReturn(false);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setOnBehalfOfUser')->once()->with('42');
    $client->shouldReceive('resource')->once()->with(ResourceType::TICKET)->andReturn($ticketResource);
    $client->shouldReceive('resource')->once()->with(ResourceType::TICKET_ARTICLE)->andReturn($articleResource);

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->twice()->andReturn($client);

    $service = new ZammadTicketService(
        $factory,
        $resolver,
        new ZammadArticleBodyRenderer,
        new ZammadTicketSearchQueryBuilder,
    );

    $service->replyToTicket($user, 315, 'Ich freue mich auf den Termin.');
});

test('replyToTicket rejects tickets that belong to another customer', function (): void {
    $user = User::factory()->make(['id' => 7]);

    $resolver = Mockery::mock(ZammadUserResolver::class);
    $resolver->shouldReceive('resolveCustomerId')->once()->with($user)->andReturn(42);

    $ticketResource = Mockery::mock(AbstractResource::class);
    $ticketResource->shouldReceive('get')->once()->with(315)->andReturnSelf();
    $ticketResource->shouldReceive('hasError')->once()->andReturn(false);
    $ticketResource->shouldReceive('getId')->once()->andReturn(315);
    $ticketResource->shouldReceive('getValues')->once()->andReturn([
        'id' => 315,
        'customer_id' => 99,
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('resource')->once()->with(ResourceType::TICKET)->andReturn($ticketResource);
    $client->shouldNotReceive('setOnBehalfOfUser');

    $factory = Mockery::mock(ZammadClientFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($client);

    $service = new ZammadTicketService(
        $factory,
        $resolver,
        new ZammadArticleBodyRenderer,
        new ZammadTicketSearchQueryBuilder,
    );

    expect(fn () => $service->replyToTicket($user, 315, 'Nicht erlaubt.'))
        ->toThrow(RuntimeException::class, 'Ticket not found or access denied.');
});
