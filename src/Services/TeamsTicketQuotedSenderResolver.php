<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\MsGraphLaravel\Services\TeamsChatMessageService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class TeamsTicketQuotedSenderResolver
{
    public function __construct(
        private readonly TeamsTicketUserResolver $userResolver,
    ) {}

    public function resolve(
        ?string $quotedSenderAzureId,
        ?string $quotedSenderName,
        ?string $actorAzureUserId,
        ?string $quotedText,
        ?string $excludeConversationId = null,
    ): ?Authenticatable {
        $customer = $this->userResolver->resolveQuotedSender($quotedSenderAzureId, $quotedSenderName);

        if ($customer instanceof Authenticatable) {
            return $customer;
        }

        if (! filled($quotedText) || ! filled($actorAzureUserId) || ! class_exists(TeamsChatMessageService::class)) {
            return null;
        }

        $lookup = app(TeamsChatMessageService::class);
        $found = $lookup->lookupForwardedMessageSender($actorAzureUserId, $quotedText, $excludeConversationId);

        if (! filled($found['azureUserId'])) {
            Log::info('Teams-Bot: Kein Original-Absender für Weiterleitung gefunden', [
                'actor_azure_user_id' => $actorAzureUserId,
                'quoted_sender_name' => $quotedSenderName,
            ]);

            return null;
        }

        return $this->userResolver->resolveQuotedSender($found['azureUserId'], $found['displayName']);
    }
}
