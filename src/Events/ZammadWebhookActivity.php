<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ZammadWebhookActivity implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $webhookCallId,
        public readonly string $phase,
        public readonly ?string $status = null,
        public readonly ?string $message = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tickets-zammad-webhooks'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'zammad.webhook.activity';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'webhook_call_id' => $this->webhookCallId,
            'phase' => $this->phase,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
