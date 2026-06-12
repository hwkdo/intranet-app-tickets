<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Support\IntranetUserSearchFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('search filter matches vorname nachname username and full name', function (): void {
    User::factory()->create([
        'active' => true,
        'vorname' => 'Hans',
        'nachname' => 'Müller',
        'username' => 'hmuelle',
        'email' => 'hans.mueller@example.com',
    ]);

    User::factory()->create([
        'active' => true,
        'vorname' => 'Anna',
        'nachname' => 'Schmidt',
        'username' => 'aschmidt',
        'email' => 'anna.schmidt@example.com',
    ]);

    $filter = new IntranetUserSearchFilter;

    expect(User::query()->tap(fn ($query) => $filter->apply($query, 'Müller'))->count())->toBe(1)
        ->and(User::query()->tap(fn ($query) => $filter->apply($query, 'Hans Müller'))->count())->toBe(1)
        ->and(User::query()->tap(fn ($query) => $filter->apply($query, 'hmuelle'))->count())->toBe(1)
        ->and(User::query()->tap(fn ($query) => $filter->apply($query, 'anna.schmidt'))->count())->toBe(1);
});
