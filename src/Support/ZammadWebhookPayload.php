<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Support;

class ZammadWebhookPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): self
    {
        return new self($payload);
    }

    public function hasArticle(): bool
    {
        $article = $this->payload['article'] ?? null;

        return is_array($article)
            && $article !== []
            && array_key_exists('id', $article);
    }

    public function isAgentPublicArticle(): bool
    {
        $article = $this->article();

        if ($article === null) {
            return false;
        }

        return ! $this->isInternal($article) && $this->isAgentSender($article);
    }

    public function isTicketStatusUpdate(): bool
    {
        return ! $this->hasArticle()
            && $this->ticket() !== null
            && $this->ticketState() !== null;
    }

    public function shouldNotifyForTicketState(): bool
    {
        $state = $this->ticketState();

        if ($state === null) {
            return false;
        }

        /** @var list<string>|null $allowedStates */
        $allowedStates = config('intranet-app-tickets.webhook.notify_states');

        if ($allowedStates === null || $allowedStates === []) {
            return true;
        }

        $normalizedState = strtolower($state);

        foreach ($allowedStates as $allowedState) {
            if (strtolower((string) $allowedState) === $normalizedState) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ticket(): ?array
    {
        $ticket = $this->payload['ticket'] ?? null;

        return is_array($ticket) ? $ticket : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function article(): ?array
    {
        if (! $this->hasArticle()) {
            return null;
        }

        /** @var array<string, mixed> $article */
        $article = $this->payload['article'];

        return $article;
    }

    public function customerEmail(): ?string
    {
        $ticket = $this->ticket();

        if ($ticket === null) {
            return null;
        }

        $email = $ticket['customer']['email'] ?? null;

        if (is_string($email) && $email !== '') {
            return $email;
        }

        return null;
    }

    public function ticketId(): ?int
    {
        $ticket = $this->ticket();
        $id = $ticket['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function ticketNumber(): ?string
    {
        $number = $this->ticket()['number'] ?? null;

        return is_scalar($number) ? (string) $number : null;
    }

    public function ticketTitle(): ?string
    {
        $title = $this->ticket()['title'] ?? null;

        return is_scalar($title) ? (string) $title : null;
    }

    public function ticketState(): ?string
    {
        $state = $this->ticket()['state'] ?? null;

        return is_scalar($state) ? (string) $state : null;
    }

    public function ticketStateLabel(): string
    {
        $state = $this->ticketState();

        if ($state === null) {
            return 'unbekannt';
        }

        return match (strtolower($state)) {
            'new' => 'Neu',
            'open' => 'Offen',
            'closed' => 'Geschlossen',
            'pending reminder' => 'Warten auf Erinnerung',
            'pending close' => 'Warten auf Schließen',
            default => ucfirst($state),
        };
    }

    public function articleId(): ?int
    {
        $article = $this->article();
        $id = $article['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function latestTicketArticleId(): ?int
    {
        $articleIds = $this->ticket()['article_ids'] ?? null;

        if (! is_array($articleIds) || $articleIds === []) {
            return null;
        }

        $id = end($articleIds);

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /**
     * @param  array<string, mixed>  $article
     */
    private function isInternal(array $article): bool
    {
        if (! array_key_exists('internal', $article)) {
            return false;
        }

        return filter_var($article['internal'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $article
     */
    private function isAgentSender(array $article): bool
    {
        return strtolower((string) ($article['sender'] ?? '')) === 'agent';
    }
}
