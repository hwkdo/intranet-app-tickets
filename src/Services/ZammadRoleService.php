<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use ZammadAPIClient\Client\Response;

class ZammadRoleService
{
    private const CACHE_KEY = 'intranet-app-tickets.zammad.roles';

    public function __construct(
        private readonly ZammadClientFactory $clientFactory,
    ) {}

    /**
     * @return Collection<int, array{id: int, name: string, active: bool}>
     */
    public function listRoles(): Collection
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return $this->fetchRolesFromApi();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<int, array{id: int, name: string, active: bool}>
     */
    public function refreshRoles(): Collection
    {
        $this->forgetCache();

        return $this->listRoles();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRole(int $roleId): array
    {
        $client = $this->clientFactory->make();
        $response = $client->get('roles/'.$roleId);

        if ($response->hasError()) {
            throw new RuntimeException($response->getError() ?? 'Zammad role fetch failed.');
        }

        $data = $response->getData();

        if (! is_array($data)) {
            throw new RuntimeException('Zammad role fetch returned an invalid response.');
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $groupPermissions  group id => access level (empty string removes)
     */
    public function updateGroupPermissions(int $roleId, array $groupPermissions): void
    {
        $role = $this->getRole($roleId);

        $groupIds = $this->normalizeGroupIdsForUpdate($role['group_ids'] ?? []);

        foreach ($groupPermissions as $groupId => $access) {
            $key = (string) $groupId;
            $access = is_string($access) ? trim($access) : '';

            if ($access === '') {
                unset($groupIds[$key]);

                continue;
            }

            $groupIds[$key] = $access;
        }

        $this->updateRole($roleId, [
            'name' => (string) ($role['name'] ?? ''),
            'active' => (bool) ($role['active'] ?? true),
            'note' => (string) ($role['note'] ?? ''),
            'default_at_signup' => (bool) ($role['default_at_signup'] ?? false),
            'permission_ids' => array_values(array_map('intval', $role['permission_ids'] ?? [])),
            'group_ids' => $groupIds,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateRole(int $roleId, array $payload): array
    {
        $client = $this->clientFactory->make();
        $response = $client->put('roles/'.$roleId, $payload);

        if ($response->hasError()) {
            throw new RuntimeException($response->getError() ?? 'Zammad role update failed.');
        }

        $data = $response->getData();

        if (! is_array($data)) {
            throw new RuntimeException('Zammad role update returned an invalid response.');
        }

        $this->forgetCache();

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function parseGroupPermissionsFromRole(array $role): array
    {
        $permissions = [];

        foreach ($this->normalizeGroupIdsForUpdate($role['group_ids'] ?? []) as $groupId => $access) {
            $permissions[$groupId] = $access;
        }

        return $permissions;
    }

    /**
     * @param  mixed  $access
     */
    public static function normalizeAccessValue(mixed $access): string
    {
        if (is_array($access)) {
            $first = $access[0] ?? '';

            return is_string($first) ? $first : '';
        }

        return is_string($access) ? $access : '';
    }

    /**
     * @return Collection<int, array{id: int, name: string, active: bool}>
     */
    private function fetchRolesFromApi(): Collection
    {
        $client = $this->clientFactory->make();
        $response = $client->get('roles');

        return $this->mapRolesResponse($response);
    }

    /**
     * @param  array<string|int, mixed>  $groupIds
     * @return array<string, string>
     */
    private function normalizeGroupIdsForUpdate(array $groupIds): array
    {
        $normalized = [];

        foreach ($groupIds as $groupId => $access) {
            $value = self::normalizeAccessValue($access);

            if ($value === '') {
                continue;
            }

            $normalized[(string) $groupId] = $value;
        }

        return $normalized;
    }

    /**
     * @return Collection<int, array{id: int, name: string, active: bool}>
     */
    private function mapRolesResponse(Response $response): Collection
    {
        if ($response->hasError()) {
            throw new RuntimeException($response->getError() ?? 'Zammad role fetch failed.');
        }

        $data = $response->getData();

        if (! is_array($data)) {
            throw new RuntimeException('Zammad role fetch returned an invalid response.');
        }

        return collect($data)
            ->map(function (mixed $role): array {
                if (! is_array($role)) {
                    return ['id' => 0, 'name' => '', 'active' => false];
                }

                return [
                    'id' => (int) ($role['id'] ?? 0),
                    'name' => (string) ($role['name'] ?? ''),
                    'active' => (bool) ($role['active'] ?? false),
                ];
            })
            ->filter(fn (array $role): bool => $role['id'] > 0 && $role['name'] !== '')
            ->sortBy('name')
            ->values();
    }
}
