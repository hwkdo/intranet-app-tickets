<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Enums\ZammadWebhookOutcomeStatus;
use Hwkdo\IntranetAppTickets\Events\ZammadWebhookActivity;
use Hwkdo\IntranetAppTickets\Models\ZammadWebhookOutcome;
use Throwable;

class ZammadWebhookOutcomeRecorder
{
    public function recordReceived(int $webhookCallId): void
    {
        ZammadWebhookActivity::dispatch(
            webhookCallId: $webhookCallId,
            phase: 'received',
        );
    }

    public function record(
        int $webhookCallId,
        ZammadWebhookOutcomeStatus $status,
        ?string $message = null,
        ?int $userId = null,
    ): ZammadWebhookOutcome {
        $outcome = ZammadWebhookOutcome::query()->updateOrCreate(
            ['webhook_call_id' => $webhookCallId],
            [
                'status' => $status,
                'message' => $message,
                'user_id' => $userId,
                'processed_at' => now(),
            ],
        );

        ZammadWebhookActivity::dispatch(
            webhookCallId: $webhookCallId,
            phase: 'processed',
            status: $status->value,
            message: $message,
        );

        return $outcome;
    }

    public function recordFailed(int $webhookCallId, ?Throwable $exception = null): ZammadWebhookOutcome
    {
        $message = $exception?->getMessage();

        return $this->record(
            webhookCallId: $webhookCallId,
            status: ZammadWebhookOutcomeStatus::Failed,
            message: $message,
        );
    }
}
