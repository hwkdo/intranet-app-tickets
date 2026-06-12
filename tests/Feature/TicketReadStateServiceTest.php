<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Models\TicketReadState;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Illuminate\Foundation\Auth\User;

it('marks tickets as unread and read', function () {
    $service = app(TicketReadStateService::class);
    $user = new class extends User
    {
        public $id = 42;

        public function getAuthIdentifier(): int
        {
            return $this->id;
        }
    };

    $service->markUnread(42, 100, '10081', 'Test Ticket', 501);
    expect(TicketReadState::query()->where('has_unread', true)->count())->toBe(1);

    $service->markRead($user, 100, 501);
    expect(TicketReadState::query()->where('has_unread', true)->count())->toBe(0);
});

it('returns unread tickets for user', function () {
    $service = app(TicketReadStateService::class);
    $user = new class extends User
    {
        public $id = 7;

        public function getAuthIdentifier(): int
        {
            return $this->id;
        }
    };

    $service->markUnread(7, 200, '20001', 'Unread Ticket', 10);

    expect($service->unreadForUser($user))->toHaveCount(1);
    expect($service->isUnread($user, 200))->toBeTrue();
});
