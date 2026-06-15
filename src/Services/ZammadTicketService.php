<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Enums\TicketFilterEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use RuntimeException;
use ZammadAPIClient\Resource\AbstractResource;
use ZammadAPIClient\ResourceType;

class ZammadTicketService
{
    public function __construct(
        private readonly ZammadClientFactory $clientFactory,
        private readonly ZammadUserResolver $userResolver,
        private readonly ZammadArticleBodyRenderer $articleBodyRenderer,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listTicketsForUser(Authenticatable $user, TicketFilterEnum $filter = TicketFilterEnum::Open): Collection
    {
        $customerId = $this->userResolver->resolveCustomerId($user);

        if ($customerId === null) {
            return collect();
        }

        $search = $this->buildSearchQuery($customerId, $filter);
        $client = $this->clientFactory->make();
        $tickets = $client->resource(ResourceType::TICKET)->search($search);

        if (! is_array($tickets)) {
            throw new RuntimeException($tickets->getError() ?? 'Zammad ticket search failed.');
        }

        return collect($tickets)
            ->map(fn (AbstractResource $ticket) => $ticket->getValues())
            ->sortByDesc('updated_at')
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTicketForUser(Authenticatable $user, int $ticketId): ?array
    {
        $customerId = $this->userResolver->resolveCustomerId($user);

        if ($customerId === null) {
            return null;
        }

        $client = $this->clientFactory->make();
        $ticket = $client->resource(ResourceType::TICKET)->get($ticketId);

        if ($ticket->hasError() || $ticket->getId() === null) {
            return null;
        }

        $values = $ticket->getValues();

        if ((int) ($values['customer_id'] ?? 0) !== $customerId) {
            return null;
        }

        return $values;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPublicArticlesForUser(Authenticatable $user, int $ticketId): array
    {
        $ticket = $this->getTicketForUser($user, $ticketId);

        if ($ticket === null) {
            return [];
        }

        $client = $this->clientFactory->make();
        $ticketResource = $client->resource(ResourceType::TICKET)->get($ticketId);

        if ($ticketResource->getId() === null) {
            return [];
        }

        $articles = $ticketResource->getTicketArticles();
        $result = [];

        foreach ($articles as $article) {
            if ($article->getValue('internal') === false) {
                $values = $article->getValues();
                $values['body'] = $this->articleBodyRenderer->render(
                    (string) ($values['body'] ?? ''),
                    $ticketId,
                    (int) ($values['id'] ?? 0),
                    $values['attachments'] ?? [],
                );
                $result[] = $values;
            }
        }

        return $result;
    }

    /**
     * @param  list<array{filename: string, data: string, mime-type: string}>  $attachments
     */
    public function createTicket(
        Authenticatable $customer,
        int $groupId,
        string $title,
        string $body,
        array $attachments = [],
    ): int {
        return $this->withZammadCustomer($customer, function (int $customerId) use ($groupId, $title, $body, $attachments): int {
            $client = $this->clientFactory->make();
            $client->setOnBehalfOfUser((string) $customerId);

            $article = [
                'subject' => $title,
                'body' => $body,
                'type' => 'web',
                'content_type' => 'text/plain',
                'internal' => false,
                'created_by_id' => $customerId,
            ];

            if ($attachments !== []) {
                $article['attachments'] = $attachments;
            }

            $ticket = $client->resource(ResourceType::TICKET);
            $ticket->setValues([
                'group_id' => $groupId,
                'title' => $title,
                'customer_id' => $customerId,
                'article' => $article,
            ]);
            $ticket->save();

            if ($ticket->hasError()) {
                throw new RuntimeException($ticket->getError() ?? 'Failed to create Zammad ticket.');
            }

            $ticketId = $ticket->getId();

            if ($ticketId === null) {
                throw new RuntimeException('Zammad ticket was created without an ID.');
            }

            return (int) $ticketId;
        });
    }

    /**
     * @param  list<string>  $tags
     */
    public function addTagsToTicket(int $ticketId, array $tags): void
    {
        $tags = collect($tags)
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tags === []) {
            return;
        }

        $client = $this->clientFactory->make();
        $tagResource = $client->resource(ResourceType::TAG);

        foreach ($tags as $tag) {
            $result = $tagResource->add($ticketId, $tag, 'Ticket');

            if ($result->hasError()) {
                throw new RuntimeException($result->getError() ?? 'Failed to add Zammad tag: '.$tag);
            }
        }
    }

    public function replyToTicket(Authenticatable $user, int $ticketId, string $body): void
    {
        $ticket = $this->getTicketForUser($user, $ticketId);

        if ($ticket === null) {
            throw new RuntimeException('Ticket not found or access denied.');
        }

        $this->withZammadCustomer($user, function (int $customerId) use ($ticketId, $body): void {
            $client = $this->clientFactory->make();
            $client->setOnBehalfOfUser((string) $customerId);

            $article = $client->resource(ResourceType::TICKET_ARTICLE);
            $article->setValues([
                'ticket_id' => $ticketId,
                'body' => $body,
                'type' => 'web',
                'content_type' => 'text/plain',
                'internal' => false,
                'created_by_id' => $customerId,
            ]);
            $article->save();

            if ($article->hasError()) {
                throw new RuntimeException($article->getError() ?? 'Failed to create ticket article.');
            }
        });
    }

    public function getAttachmentContent(Authenticatable $user, int $ticketId, int $articleId, int $attachmentId): ?string
    {
        if ($this->getTicketForUser($user, $ticketId) === null) {
            return null;
        }

        $client = $this->clientFactory->make();
        $article = $client->resource(ResourceType::TICKET_ARTICLE)->get($articleId);

        if ($article->getId() === null) {
            return null;
        }

        if ((int) $article->getValue('ticket_id') !== $ticketId) {
            return null;
        }

        return $article->getAttachmentContent($attachmentId);
    }

    /**
     * @return array{content_type: string, filename: string}|null
     */
    public function getAttachmentMeta(Authenticatable $user, int $ticketId, int $articleId, int $attachmentId): ?array
    {
        if ($this->getTicketForUser($user, $ticketId) === null) {
            return null;
        }

        $client = $this->clientFactory->make();
        $article = $client->resource(ResourceType::TICKET_ARTICLE)->get($articleId);

        if ($article->getId() === null) {
            return null;
        }

        $values = $article->getValues();

        foreach ($values['attachments'] ?? [] as $attachment) {
            if ((int) ($attachment['id'] ?? 0) !== $attachmentId) {
                continue;
            }

            $preferences = $attachment['preferences'] ?? [];

            return [
                'content_type' => $preferences['Content-Type']
                    ?? ($preferences['Mime-Type'] ?? 'application/octet-stream'),
                'filename' => $attachment['filename'] ?? 'attachment',
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getZammadUser(int $zammadUserId): ?array
    {
        $client = $this->clientFactory->make();
        $user = $client->resource(ResourceType::USER)->get($zammadUserId);

        if ($user->hasError() || $user->getId() === null) {
            return null;
        }

        return $user->getValues();
    }

    /**
     * @template TReturn
     *
     * @param  callable(int): TReturn  $callback
     * @return TReturn
     */
    private function withZammadCustomer(Authenticatable $customer, callable $callback): mixed
    {
        $customerId = $this->requireCustomerId($customer);

        try {
            return $callback($customerId);
        } catch (RuntimeException $exception) {
            if (! $this->userResolver->forgetMappingIfStaleUserError($customer, $exception->getMessage())) {
                throw $exception;
            }
        }

        return $callback($this->requireCustomerId($customer));
    }

    private function requireCustomerId(Authenticatable $customer): int
    {
        $customerId = $this->userResolver->resolveCustomerId($customer);

        if ($customerId === null) {
            throw new RuntimeException('Zammad customer mapping not found.');
        }

        return $customerId;
    }

    private function buildSearchQuery(int $customerId, TicketFilterEnum $filter): string
    {
        $search = 'customer_id:'.$customerId;

        if ($filter === TicketFilterEnum::Open) {
            $closedIds = config('intranet-app-tickets.closed_state_ids', [4, 5]);

            foreach ($closedIds as $stateId) {
                $search .= ' AND !(state_id:'.$stateId.')';
            }
        }

        if ($filter === TicketFilterEnum::Closed) {
            $closedIds = config('intranet-app-tickets.closed_state_ids', [4, 5]);
            $conditions = collect($closedIds)
                ->map(fn (int $stateId) => 'state_id:'.$stateId)
                ->implode(' OR ');

            $search .= ' AND ('.$conditions.')';
        }

        return $search;
    }
}
