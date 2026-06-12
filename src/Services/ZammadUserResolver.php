<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Models\ZammadUserMapping;
use Hwkdo\IntranetAppTickets\Support\ZammadUserProfileMapper;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZammadAPIClient\Resource\AbstractResource;
use ZammadAPIClient\ResourceType;

class ZammadUserResolver
{
    public function __construct(
        private readonly ZammadClientFactory $clientFactory,
        private readonly ZammadUserProfileMapper $profileMapper,
        private readonly TicketsAppSettingsStore $settingsStore,
    ) {}

    public function resolveCustomerId(Authenticatable $user): ?int
    {
        $mapping = ZammadUserMapping::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if ($mapping !== null) {
            return (int) $mapping->zammad_customer_id;
        }

        try {
            return $this->provisionCustomer($user);
        } catch (RuntimeException $exception) {
            Log::info('Zammad customer resolution failed', [
                'user_id' => $user->getAuthIdentifier(),
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function provisionCustomer(Authenticatable $user): int
    {
        $mapping = ZammadUserMapping::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if ($mapping !== null) {
            return (int) $mapping->zammad_customer_id;
        }

        $email = $this->profileMapper->resolveEmail($user);

        if ($email === null) {
            throw new RuntimeException('Der Intranet-Benutzer hat keine E-Mail-Adresse für Zammad.');
        }

        $zammadCustomerId = $this->findZammadUserIdByEmail($email);

        if ($zammadCustomerId === null) {
            $createdId = $this->createZammadUser($user, $email);

            if ($createdId === null) {
                throw new RuntimeException('Zammad-Benutzer konnte nicht angelegt werden.');
            }

            $zammadCustomerId = $createdId;
        }

        $this->storeMapping($user, $email, $zammadCustomerId);
        app(ZammadUserRoleService::class)->forgetCache();

        return $zammadCustomerId;
    }

    public function forgetMapping(Authenticatable $user): void
    {
        ZammadUserMapping::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }

    private function findZammadUserIdByEmail(string $email): ?int
    {
        $client = $this->clientFactory->make();
        $zammadUsers = $client->resource(ResourceType::USER)->search($email);

        if (! is_array($zammadUsers) || $zammadUsers === []) {
            return null;
        }

        $normalizedEmail = mb_strtolower($email);

        $exactMatches = collect($zammadUsers)
            ->filter(function (AbstractResource $zammadUser) use ($normalizedEmail): bool {
                $candidate = mb_strtolower(trim((string) ($zammadUser->getValue('email') ?? '')));

                return $candidate === $normalizedEmail;
            })
            ->values();

        if ($exactMatches->count() === 1) {
            return (int) $exactMatches->first()->getId();
        }

        if ($exactMatches->count() > 1) {
            Log::warning('Zammad user search returned multiple exact email matches', [
                'email' => $email,
                'result_count' => $exactMatches->count(),
            ]);

            return (int) $exactMatches->first()->getId();
        }

        if (count($zammadUsers) === 1) {
            return (int) $zammadUsers[0]->getId();
        }

        Log::info('Zammad user search did not return a unique match', [
            'email' => $email,
            'result_count' => count($zammadUsers),
        ]);

        return null;
    }

    private function createZammadUser(Authenticatable $user, string $email): ?int
    {
        $roleId = $this->settingsStore->zammadIntranetUserRoleId();

        if ($roleId === null) {
            throw new RuntimeException(
                'Für den Intranet-Benutzer existiert kein Zammad-Konto. Bitte im Tickets-Admin eine Intranet-Benutzer-Rolle konfigurieren.'
            );
        }

        try {
            $payload = $this->profileMapper->toCreatePayload($user, $roleId);
        } catch (\InvalidArgumentException $exception) {
            Log::warning('Zammad user auto-provision skipped', [
                'user_id' => $user->getAuthIdentifier(),
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }

        $client = $this->clientFactory->make();
        $userResource = $client->resource(ResourceType::USER);
        $userResource->setValues($payload);
        $userResource->save();

        if ($userResource->hasError()) {
            throw new RuntimeException($userResource->getError() ?? 'Zammad-Benutzer konnte nicht angelegt werden.');
        }

        $zammadUserId = $userResource->getId();

        if ($zammadUserId === null) {
            throw new RuntimeException('Zammad-Benutzer wurde ohne ID angelegt.');
        }

        Log::info('Zammad user auto-provisioned', [
            'user_id' => $user->getAuthIdentifier(),
            'email' => $email,
            'zammad_user_id' => $zammadUserId,
            'role_id' => $roleId,
        ]);

        return (int) $zammadUserId;
    }

    private function storeMapping(Authenticatable $user, string $email, int $zammadCustomerId): void
    {
        ZammadUserMapping::query()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            [
                'zammad_customer_id' => $zammadCustomerId,
                'zammad_email' => $email,
                'resolved_at' => now(),
            ],
        );
    }
}
