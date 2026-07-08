<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Jobs;

use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketContentGenerator;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketQuotedAttachmentResolver;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketQuotedSenderResolver;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketUserResolver;
use Hwkdo\IntranetAppTickets\Services\TicketSubmissionService;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsBotServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CreateTicketFromTeamsMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    public function __construct(
        public readonly ?string $upn,
        public readonly ?string $azureUserId,
        public readonly ?string $displayName,
        public readonly string $rawContent,
        public readonly string $fallbackSubject,
        public readonly string $fallbackBody,
        public readonly string $sourceLabel,
        public readonly bool $contentFromQuote = false,
        public readonly ?string $quotedSenderAzureId = null,
        public readonly ?string $quotedSenderName = null,
        public readonly ?string $quotedMessageId = null,
        public readonly ?string $quotedText = null,
        public readonly array $activity = [],
        public readonly array $conversationRef = [],
    ) {}

    public function handle(
        TeamsTicketUserResolver $userResolver,
        TeamsTicketQuotedSenderResolver $quotedSenderResolver,
        TeamsTicketContentGenerator $contentGenerator,
        TicketSubmissionService $submissionService,
        TeamsTicketQuotedAttachmentResolver $attachmentResolver,
    ): void {
        $actor = $userResolver->resolve($this->upn, $this->azureUserId);

        if (! $actor instanceof Authenticatable) {
            $this->reply('Ich konnte dich im Intranet leider nicht zuordnen. Bitte erstelle das Ticket direkt im Intranet.');

            return;
        }

        $customer = $this->resolveCustomer($userResolver, $quotedSenderResolver, $actor);

        if ($customer === null) {
            return;
        }

        $category = $this->resolveCategory();

        if (! $category instanceof TicketCategory) {
            $this->reply('Die Ticket-Erstellung über Teams ist derzeit nicht möglich (keine passende Kategorie konfiguriert).');

            return;
        }

        if ($category->requires_approval) {
            $this->reply('Diese Ticketart erfordert eine Genehmigung und kann nicht über den Bot erstellt werden. Bitte nutze dafür das Intranet.');

            return;
        }

        try {
            $generated = $contentGenerator->generate(
                rawContent: $this->rawContent,
                displayName: $this->displayName,
                sourceLabel: $this->sourceLabel,
                fallbackSubject: $this->fallbackSubject,
                fallbackBody: $this->fallbackBody,
            );

            $files = $attachmentResolver->resolve(
                $this->resolveConversationId(),
                $this->quotedMessageId,
            );

            $ticketRequest = $submissionService->submit(
                category: $category,
                formData: [
                    'betreff' => Str::limit($generated->subject, 150, ''),
                    'inhalt' => $this->composeBody($generated->body, $generated->generatedByAi),
                    'on_behalf_of_user_id' => $customer->getAuthIdentifier(),
                ],
                files: $files,
                requester: $actor,
                onBehalfOf: $customer,
            );

            $this->reply($this->successMessage($ticketRequest, $actor, $customer));
        } catch (Throwable $exception) {
            Log::error('Teams-Bot Ticket-Erstellung fehlgeschlagen', [
                'upn' => $this->upn,
                'quoted_sender_azure_id' => $this->quotedSenderAzureId,
                'message' => $exception->getMessage(),
            ]);

            $this->reply('Beim Erstellen deines Tickets ist ein Fehler aufgetreten. Bitte versuche es später erneut oder nutze das Intranet.');
        }
    }

    private function resolveCustomer(
        TeamsTicketUserResolver $userResolver,
        TeamsTicketQuotedSenderResolver $quotedSenderResolver,
        Authenticatable $actor,
    ): ?Authenticatable {
        if (! $this->contentFromQuote) {
            return $actor;
        }

        $quotedCustomer = $quotedSenderResolver->resolve(
            quotedSenderAzureId: $this->quotedSenderAzureId,
            quotedSenderName: $this->quotedSenderName,
            actorAzureUserId: $this->azureUserId,
            quotedText: $this->quotedText ?? $this->rawContent,
            excludeConversationId: $this->resolveConversationId(),
        );

        if ($quotedCustomer instanceof Authenticatable) {
            return $quotedCustomer;
        }

        Log::warning('Teams-Bot: Zitat-Autor konnte im Intranet nicht zugeordnet werden', [
            'quoted_sender_name' => $this->quotedSenderName,
            'quoted_sender_azure_id' => $this->quotedSenderAzureId,
            'actor_upn' => $this->upn,
        ]);

        $senderLabel = filled($this->quotedSenderName) ? $this->quotedSenderName : 'den Autor der weitergeleiteten oder zitierten Nachricht';

        $this->reply(
            'Ich konnte '.$senderLabel.' im Intranet nicht zuordnen. '
            .'Das Ticket kann deshalb nicht für diese Person erstellt werden. '
            .'Bitte erstelle das Ticket direkt im Intranet oder formuliere dein Anliegen ohne Bezug auf die Nachricht eines anderen Nutzers.'
        );

        return null;
    }

    private function resolveCategory(): ?TicketCategory
    {
        $slug = config('intranet-app-tickets.teams_bot.default_category_slug');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $category = TicketCategory::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->first();

        if (! $category instanceof TicketCategory) {
            return null;
        }

        return $category->isConfiguredForDispatch() ? $category : null;
    }

    private function composeBody(string $ticketBody, bool $generatedByAi): string
    {
        $parts = [trim($ticketBody)];

        $originalTeamsText = trim($this->rawContent);

        if ($generatedByAi && $originalTeamsText !== '') {
            $parts[] = "---\nOriginaltext aus Microsoft Teams:\n".$originalTeamsText;
        }

        $parts[] = implode("\n", array_filter([
            '---',
            'Erstellt über '.$this->sourceLabel,
            $this->displayName !== null ? 'Ausgelöst von: '.$this->displayName.($this->upn !== null ? ' ('.$this->upn.')' : '') : null,
            'Am: '.now()->format('d.m.Y H:i'),
        ]));

        return trim(implode("\n\n", array_filter($parts)));
    }

    private function successMessage(
        TicketRequest $ticketRequest,
        Authenticatable $actor,
        Authenticatable $customer,
    ): string {
        $ticketId = $ticketRequest->zammad_ticket_id;
        $createdForOtherUser = $actor->getAuthIdentifier() !== $customer->getAuthIdentifier();
        $customerName = $this->userDisplayName($customer);

        if ($ticketRequest->category->transmission === TransmissionChannel::Zammad && $ticketId !== null) {
            $baseUrl = rtrim((string) config('intranet-app-tickets.zammad.url'), '/');
            $link = $baseUrl !== '' ? "\n".$baseUrl.'/#ticket/zoom/'.$ticketId : '';

            if ($createdForOtherUser) {
                return 'Das Ticket #'.$ticketId.' für '.$customerName.' wurde erstellt.'
                    ."\nBetreff: ".$ticketRequest->subject
                    .$link;
            }

            return 'Dein Ticket #'.$ticketId.' wurde erstellt.'
                ."\nBetreff: ".$ticketRequest->subject
                .$link;
        }

        if ($createdForOtherUser) {
            return 'Das Ticket für '.$customerName.' wurde erstellt.'."\nBetreff: ".$ticketRequest->subject;
        }

        return 'Dein Ticket wurde erstellt.'."\nBetreff: ".$ticketRequest->subject;
    }

    private function userDisplayName(Authenticatable $user): string
    {
        if (method_exists($user, 'getAttribute')) {
            $name = $user->getAttribute('name');

            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return 'Nutzer #'.$user->getAuthIdentifier();
    }

    private function resolveConversationId(): ?string
    {
        $conversationId = $this->conversationRef['conversationId'] ?? null;

        if (is_string($conversationId) && trim($conversationId) !== '') {
            return trim($conversationId);
        }

        $conversation = $this->activity['conversation'] ?? null;

        if (! is_array($conversation)) {
            return null;
        }

        $id = $conversation['id'] ?? null;

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    private function reply(string $text): void
    {
        if (! interface_exists(MsGraphTeamsBotServiceInterface::class)
            || ! app()->bound(MsGraphTeamsBotServiceInterface::class)) {
            return;
        }

        try {
            app(MsGraphTeamsBotServiceInterface::class)
                ->replyToIncomingTeamsMessage($this->activity, $this->conversationRef, $text);
        } catch (Throwable $exception) {
            Log::warning('Teams-Bot Ticket-Antwort konnte nicht gesendet werden', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
