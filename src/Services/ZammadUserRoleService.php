<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use ZammadAPIClient\Resource\AbstractResource;
use ZammadAPIClient\ResourceType;

class ZammadUserRoleService
{
    private const ROLE_MAP_CACHE_KEY = 'intranet-app-tickets.zammad.users.role-map';

    public function __construct(
        private readonly ZammadClientFactory $clientFactory,
        private readonly ZammadUserResolver $userResolver,
    ) {}

    /**
     * @return Collection<string, list<int>>
     */
    public function getEmailToRoleIdsMap(): Collection
    {
        return Cache::remember(self::ROLE_MAP_CACHE_KEY, 300, function () {
            return $this->fetchEmailToRoleIdsMap();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::ROLE_MAP_CACHE_KEY);
    }

    public function emailHasRole(?string $email, int $roleId, ?Collection $roleMap = null): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        $roleIds = ($roleMap ?? $this->getEmailToRoleIdsMap())->get(mb_strtolower($email), []);

        return in_array($roleId, $roleIds, true);
    }

    public function assignRoleToUser(Authenticatable $user, int $roleId): void
    {
        $zammadUserId = $this->userResolver->resolveCustomerId($user);

        if ($zammadUserId === null) {
            throw new RuntimeException('Für den Benutzer wurde kein Zammad-Konto gefunden.');
        }

        $client = $this->clientFactory->make();
        $userResource = $client->resource(ResourceType::USER)->get($zammadUserId);

        if ($userResource->hasError() || $userResource->getId() === null) {
            throw new RuntimeException($userResource->getError() ?? 'Zammad-Benutzer konnte nicht geladen werden.');
        }

        $roleIds = collect($userResource->getValues()['role_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if (in_array($roleId, $roleIds, true)) {
            return;
        }

        $roleIds[] = $roleId;

        $userResource->setValues(['role_ids' => $roleIds]);
        $userResource->save();

        if ($userResource->hasError()) {
            throw new RuntimeException($userResource->getError() ?? 'Zammad-Rolle konnte nicht zugewiesen werden.');
        }

        $this->forgetCache();
    }

    /**
     * @return Collection<string, list<int>>
     */
    private function fetchEmailToRoleIdsMap(): Collection
    {
        $client = $this->clientFactory->make();
        $users = $client->resource(ResourceType::USER)->all();

        if (! is_array($users)) {
            throw new RuntimeException($users->getError() ?? 'Zammad user fetch failed.');
        }

        return collect($users)
            ->map(function (AbstractResource $user): ?array {
                $values = $user->getValues();
                $email = mb_strtolower(trim((string) ($values['email'] ?? '')));

                if ($email === '') {
                    return null;
                }

                $roleIds = collect($values['role_ids'] ?? [])
                    ->map(fn (mixed $id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->values()
                    ->all();

                return [
                    'email' => $email,
                    'role_ids' => $roleIds,
                ];
            })
            ->filter()
            ->mapWithKeys(fn (array $entry): array => [$entry['email'] => $entry['role_ids']]);
    }
}
