<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Hwkdo\IntranetAppTickets\Enums\TicketListItemType;

readonly class TicketListItem
{
    public function __construct(
        public TicketListItemType $type,
        public int $id,
        public ?string $number,
        public string $title,
        public string $statusLabel,
        public ?CarbonInterface $updatedAt,
        public string $url,
        public bool $isUnread = false,
        public ?string $badge = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'statusLabel' => $this->statusLabel,
            'updatedAt' => $this->updatedAt?->toIso8601String(),
            'url' => $this->url,
            'isUnread' => $this->isUnread,
            'badge' => $this->badge,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: TicketListItemType::from((string) $data['type']),
            id: (int) $data['id'],
            number: isset($data['number']) ? (string) $data['number'] : null,
            title: (string) $data['title'],
            statusLabel: (string) $data['statusLabel'],
            updatedAt: isset($data['updatedAt']) ? Carbon::parse((string) $data['updatedAt']) : null,
            url: (string) $data['url'],
            isUnread: (bool) ($data['isUnread'] ?? false),
            badge: isset($data['badge']) ? (string) $data['badge'] : null,
        );
    }
}
