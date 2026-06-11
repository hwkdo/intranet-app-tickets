<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Models;

use Hwkdo\IntranetAppTickets\Enums\ZammadWebhookOutcomeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\WebhookClient\Models\WebhookCall;

class ZammadWebhookOutcome extends Model
{
    protected $table = 'intranet_app_tickets_webhook_outcomes';

    protected $fillable = [
        'webhook_call_id',
        'status',
        'message',
        'user_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ZammadWebhookOutcomeStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function webhookCall(): BelongsTo
    {
        return $this->belongsTo(WebhookCall::class, 'webhook_call_id');
    }

    public function user(): BelongsTo
    {
        $userModel = config('intranet-app-tickets.user_model');

        return $this->belongsTo($userModel, 'user_id');
    }
}
