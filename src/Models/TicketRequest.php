<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Models;

use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketRequest extends Model
{
    protected $table = 'intranet_app_ticket_requests';

    protected $fillable = [
        'ticket_category_id',
        'requested_by_user_id',
        'on_behalf_of_user_id',
        'subject',
        'body',
        'form_data',
        'status',
        'rejection_reason',
        'approval_note',
        'approved_by_user_id',
        'approved_at',
        'dispatched_at',
        'zammad_ticket_id',
        'dispatch_error',
    ];

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'status' => TicketRequestStatus::class,
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'zammad_ticket_id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function requester(): BelongsTo
    {
        $userModel = config('intranet-app-tickets.user_model');

        return $this->belongsTo($userModel, 'requested_by_user_id');
    }

    public function onBehalfOf(): BelongsTo
    {
        $userModel = config('intranet-app-tickets.user_model');

        return $this->belongsTo($userModel, 'on_behalf_of_user_id');
    }

    public function approver(): BelongsTo
    {
        $userModel = config('intranet-app-tickets.user_model');

        return $this->belongsTo($userModel, 'approved_by_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketRequestAttachment::class, 'ticket_request_id');
    }

    public function customerUserId(): int
    {
        return (int) ($this->on_behalf_of_user_id ?? $this->requested_by_user_id);
    }
}
