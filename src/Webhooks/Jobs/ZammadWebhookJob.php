<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Webhooks\Jobs;

use Hwkdo\IntranetAppTickets\Enums\ZammadWebhookOutcomeStatus;
use Hwkdo\IntranetAppTickets\Events\TicketUpdated;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Hwkdo\IntranetAppTickets\Services\ZammadWebhookOutcomeRecorder;
use Hwkdo\IntranetAppTickets\Support\ZammadWebhookPayload;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use Throwable;

class ZammadWebhookJob extends ProcessWebhookJob
{
    public function handle(
        TicketReadStateService $readStateService,
        ZammadWebhookOutcomeRecorder $outcomeRecorder,
    ): void {
        $webhook = ZammadWebhookPayload::from($this->webhookCall->payload);
        $webhookCallId = (int) $this->webhookCall->id;

        if ($webhook->hasArticle()) {
            $this->handleAgentArticle($webhook, $readStateService, $outcomeRecorder, $webhookCallId);

            return;
        }

        if ($webhook->isTicketStatusUpdate()) {
            $this->handleTicketStatusUpdate($webhook, $readStateService, $outcomeRecorder, $webhookCallId);

            return;
        }

        $outcomeRecorder->record(
            webhookCallId: $webhookCallId,
            status: ZammadWebhookOutcomeStatus::SkippedUnsupported,
            message: 'Kein verarbeitbarer Artikel oder Ticket-Status im Payload.',
        );
    }

    private function handleAgentArticle(
        ZammadWebhookPayload $webhook,
        TicketReadStateService $readStateService,
        ZammadWebhookOutcomeRecorder $outcomeRecorder,
        int $webhookCallId,
    ): void {
        $article = $webhook->article();

        if ($article === null) {
            $outcomeRecorder->record(
                webhookCallId: $webhookCallId,
                status: ZammadWebhookOutcomeStatus::SkippedUnsupported,
                message: 'Artikel-Payload unvollständig.',
            );

            return;
        }

        if (filter_var($article['internal'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $outcomeRecorder->record(
                webhookCallId: $webhookCallId,
                status: ZammadWebhookOutcomeStatus::SkippedInternal,
                message: 'Interner Kommentar wird nicht an Kunden weitergegeben.',
            );

            return;
        }

        if (strtolower((string) ($article['sender'] ?? '')) !== 'agent') {
            $outcomeRecorder->record(
                webhookCallId: $webhookCallId,
                status: ZammadWebhookOutcomeStatus::SkippedUnsupported,
                message: 'Nur öffentliche Agent-Antworten lösen Benachrichtigungen aus.',
            );

            return;
        }

        $this->notifyUser(
            webhook: $webhook,
            readStateService: $readStateService,
            outcomeRecorder: $outcomeRecorder,
            webhookCallId: $webhookCallId,
            latestArticleId: $webhook->articleId(),
            ticketTitle: $webhook->ticketTitle(),
            successMessage: fn (Authenticatable $user, string $customerEmail) => "Neue Agent-Antwort an {$customerEmail} (User #{$user->getAuthIdentifier()}).",
            logContext: ['type' => 'agent_article'],
        );
    }

    private function handleTicketStatusUpdate(
        ZammadWebhookPayload $webhook,
        TicketReadStateService $readStateService,
        ZammadWebhookOutcomeRecorder $outcomeRecorder,
        int $webhookCallId,
    ): void {
        if (! $webhook->shouldNotifyForTicketState()) {
            $outcomeRecorder->record(
                webhookCallId: $webhookCallId,
                status: ZammadWebhookOutcomeStatus::SkippedStatus,
                message: 'Status „'.$webhook->ticketStateLabel().'“ ist nicht für Benachrichtigungen konfiguriert.',
            );

            return;
        }

        $ticketTitle = $webhook->ticketTitle();

        if ($ticketTitle !== null && $webhook->ticketState() !== null) {
            $ticketTitle .= ' · '.$webhook->ticketStateLabel();
        }

        $this->notifyUser(
            webhook: $webhook,
            readStateService: $readStateService,
            outcomeRecorder: $outcomeRecorder,
            webhookCallId: $webhookCallId,
            latestArticleId: $webhook->latestTicketArticleId(),
            ticketTitle: $ticketTitle,
            successMessage: fn (Authenticatable $user, string $customerEmail) => 'Status „'.$webhook->ticketStateLabel().'“ für '.$customerEmail.' (User #'.$user->getAuthIdentifier().').',
            logContext: ['type' => 'ticket_status', 'state' => $webhook->ticketState()],
        );
    }

    /**
     * @param  callable(Authenticatable, string): string  $successMessage
     * @param  array<string, mixed>  $logContext
     */
    private function notifyUser(
        ZammadWebhookPayload $webhook,
        TicketReadStateService $readStateService,
        ZammadWebhookOutcomeRecorder $outcomeRecorder,
        int $webhookCallId,
        ?int $latestArticleId,
        ?string $ticketTitle,
        callable $successMessage,
        array $logContext,
    ): void {
        $customerEmail = $webhook->customerEmail();

        if ($customerEmail === null) {
            $outcomeRecorder->record(
                webhookCallId: $webhookCallId,
                status: ZammadWebhookOutcomeStatus::SkippedNoEmail,
                message: 'Keine customer.email im Ticket-Payload.',
            );

            return;
        }

        $userModel = config('intranet-app-tickets.user_model');
        $user = $userModel::query()->where('email', $customerEmail)->first();

        if ($user === null) {
            $outcomeRecorder->record(
                webhookCallId: $webhookCallId,
                status: ZammadWebhookOutcomeStatus::SkippedNoUser,
                message: "Kein Intranet-User für {$customerEmail}.",
            );

            return;
        }

        $ticketId = $webhook->ticketId();

        if ($ticketId === null) {
            $outcomeRecorder->record(
                webhookCallId: $webhookCallId,
                status: ZammadWebhookOutcomeStatus::SkippedNoTicket,
                message: 'Keine ticket.id im Payload.',
            );

            return;
        }

        $readStateService->markUnread(
            userId: (int) $user->getAuthIdentifier(),
            zammadTicketId: $ticketId,
            ticketNumber: $webhook->ticketNumber(),
            ticketTitle: $ticketTitle,
            latestArticleId: $latestArticleId,
        );

        TicketUpdated::dispatch(
            userId: (int) $user->getAuthIdentifier(),
            ticketId: $ticketId,
            ticketNumber: $webhook->ticketNumber() ?? (string) $ticketId,
            ticketTitle: $webhook->ticketTitle() ?? '',
        );

        $outcomeRecorder->record(
            webhookCallId: $webhookCallId,
            status: ZammadWebhookOutcomeStatus::Processed,
            message: $successMessage($user, $customerEmail),
            userId: (int) $user->getAuthIdentifier(),
        );

        Log::info('Zammad webhook processed', array_merge([
            'webhook_call_id' => $webhookCallId,
            'user_id' => $user->getAuthIdentifier(),
            'ticket_id' => $ticketId,
        ], $logContext));
    }

    public function failed(?Throwable $exception): void
    {
        app(ZammadWebhookOutcomeRecorder::class)->recordFailed(
            webhookCallId: (int) $this->webhookCall->id,
            exception: $exception,
        );
    }
}
