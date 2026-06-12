<?php

declare(strict_types=1);

use App\Models\Gvp;
use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppTickets\Models\TicketGvpTag;
use Hwkdo\IntranetAppTickets\Models\TicketStandortTag;
use Hwkdo\IntranetAppTickets\Services\TicketUserZammadTagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ticketsTagStandort(): Standort
{
    return Standort::query()->create([
        'name' => 'Dortmund',
        'extension' => 'DO',
        'strasse' => 'Musterstraße 1',
        'ort' => 'Dortmund',
        'plz' => '44135',
    ]);
}

test('resolver returns configured standort and gvp tags for user', function (): void {
    $standort = ticketsTagStandort();
    $gvp = Gvp::factory()->create(['name' => 'IT']);

    TicketStandortTag::query()->create([
        'standort_id' => $standort->id,
        'tag' => 'standort-do',
    ]);

    TicketGvpTag::query()->create([
        'gvp_id' => $gvp->id,
        'tag' => 'gvp-it',
    ]);

    $user = User::factory()->create([
        'active' => true,
        'standort_id' => $standort->id,
        'gvp_id' => $gvp->id,
    ]);

    $tags = app(TicketUserZammadTagResolver::class)->resolveForUser($user);

    expect($tags)->toBe(['standort-do', 'gvp-it']);
});

test('resolver skips entities without configured tag', function (): void {
    $standort = ticketsTagStandort();
    $gvp = Gvp::factory()->create();

    TicketStandortTag::query()->create([
        'standort_id' => $standort->id,
        'tag' => 'standort-do',
    ]);

    $user = User::factory()->create([
        'active' => true,
        'standort_id' => $standort->id,
        'gvp_id' => $gvp->id,
    ]);

    $tags = app(TicketUserZammadTagResolver::class)->resolveForUser($user);

    expect($tags)->toBe(['standort-do']);
});

test('resolver returns empty list when user has no mappings', function (): void {
    $user = User::factory()->create(['active' => true]);

    expect(app(TicketUserZammadTagResolver::class)->resolveForUser($user))->toBe([]);
});
