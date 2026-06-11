<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReadState extends Model
{
    protected $table = 'intranet_app_tickets_read_states';

    protected $fillable = [
        'user_id',
        'zammad_ticket_id',
        'ticket_number',
        'ticket_title',
        'last_read_article_id',
        'latest_article_id',
        'has_unread',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'has_unread' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        $userModel = config('intranet-app-tickets.user_model');

        return $this->belongsTo($userModel, 'user_id');
    }
}
