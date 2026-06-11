<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Models\TicketReadState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class TicketReadStateService
{
    public function markUnread(
        int $userId,
        int $zammadTicketId,
        ?string $ticketNumber,
        ?string $ticketTitle,
        ?int $latestArticleId,
    ): TicketReadState {
        return TicketReadState::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'zammad_ticket_id' => $zammadTicketId,
            ],
            [
                'ticket_number' => $ticketNumber,
                'ticket_title' => $ticketTitle,
                'latest_article_id' => $latestArticleId,
                'has_unread' => true,
                'last_notified_at' => now(),
            ],
        );
    }

    public function markRead(Authenticatable $user, int $zammadTicketId, ?int $lastArticleId = null): void
    {
        TicketReadState::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('zammad_ticket_id', $zammadTicketId)
            ->update([
                'has_unread' => false,
                'last_read_article_id' => $lastArticleId,
            ]);
    }

    /**
     * @return Collection<int, TicketReadState>
     */
    public function unreadForUser(Authenticatable $user): Collection
    {
        return TicketReadState::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('has_unread', true)
            ->orderByDesc('last_notified_at')
            ->get();
    }

    public function isUnread(Authenticatable $user, int $zammadTicketId): bool
    {
        return TicketReadState::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('zammad_ticket_id', $zammadTicketId)
            ->where('has_unread', true)
            ->exists();
    }
}
