<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Hwkdo\IntranetAppTickets\Support\ZammadTicketSearchQueryBuilder;

beforeEach(function (): void {
    config(['intranet-app-tickets.closed_state_ids' => [4, 5]]);
});

test('builds customer scoped open filter query without search term', function (): void {
    $builder = new ZammadTicketSearchQueryBuilder;

    expect($builder->build(42, TicketFilterEnum::Open))
        ->toBe('customer_id:42 AND !(state_id:4) AND !(state_id:5)');
});

test('builds customer scoped closed filter query without search term', function (): void {
    $builder = new ZammadTicketSearchQueryBuilder;

    expect($builder->build(42, TicketFilterEnum::Closed))
        ->toBe('customer_id:42 AND (state_id:4 OR state_id:5)');
});

test('builds numeric search as ticket number prefix query', function (): void {
    $builder = new ZammadTicketSearchQueryBuilder;

    expect($builder->build(42, TicketFilterEnum::All, '3138881'))
        ->toBe('customer_id:42 AND (number:3138881*)');
});

test('builds text search across title and article body', function (): void {
    $builder = new ZammadTicketSearchQueryBuilder;

    expect($builder->build(42, TicketFilterEnum::All, 'Hardware Upgrade'))
        ->toBe('customer_id:42 AND (title:"Hardware Upgrade" OR article.body:"Hardware Upgrade")');
});

test('escapes quotes in search terms', function (): void {
    $builder = new ZammadTicketSearchQueryBuilder;

    expect($builder->build(42, TicketFilterEnum::All, 'Alpha "Beta"'))
        ->toBe('customer_id:42 AND (title:"Alpha \"Beta\"" OR article.body:"Alpha \"Beta\"")');
});

test('normalizes empty search terms to null', function (): void {
    $builder = new ZammadTicketSearchQueryBuilder;

    expect($builder->normalizeSearchTerm('   '))->toBeNull();
});
