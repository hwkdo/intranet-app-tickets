<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use ZammadAPIClient\Resource\AbstractResource;
use ZammadAPIClient\ResourceType;

class ZammadGroupService
{
    private const CACHE_KEY = 'intranet-app-tickets.zammad.groups';

    public function __construct(
        private readonly ZammadClientFactory $clientFactory,
    ) {}

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function listGroups(): Collection
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return $this->fetchGroupsFromApi();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function refreshGroups(): Collection
    {
        $this->forgetCache();

        return $this->listGroups();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function fetchGroupsFromApi(): Collection
    {
        $client = $this->clientFactory->make();
        $groups = $client->resource(ResourceType::GROUP)->all();

        if (! is_array($groups)) {
            throw new RuntimeException($groups->getError() ?? 'Zammad group fetch failed.');
        }

        return collect($groups)
            ->map(function (AbstractResource $group): array {
                $values = $group->getValues();

                return [
                    'id' => (int) ($values['id'] ?? 0),
                    'name' => (string) ($values['name'] ?? ''),
                ];
            })
            ->filter(fn (array $group): bool => $group['id'] > 0 && $group['name'] !== '')
            ->sortBy('name')
            ->values();
    }
}
