<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Models\ZammadUserMapping;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use ZammadAPIClient\ResourceType;

class ZammadUserResolver
{
    public function __construct(
        private readonly ZammadClientFactory $clientFactory,
    ) {}

    public function resolveCustomerId(Authenticatable $user): ?int
    {
        $mapping = ZammadUserMapping::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        if ($mapping !== null) {
            return (int) $mapping->zammad_customer_id;
        }

        $email = $user->email ?? null;

        if ($email === null || $email === '') {
            return null;
        }

        $client = $this->clientFactory->make();
        $zammadUsers = $client->resource(ResourceType::USER)->search($email);

        if (! is_array($zammadUsers) || count($zammadUsers) !== 1) {
            Log::info('Zammad user mapping failed', [
                'user_id' => $user->getAuthIdentifier(),
                'email' => $email,
                'result_count' => is_array($zammadUsers) ? count($zammadUsers) : 0,
            ]);

            return null;
        }

        $zammadCustomerId = (int) $zammadUsers[0]->getId();

        ZammadUserMapping::query()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            [
                'zammad_customer_id' => $zammadCustomerId,
                'zammad_email' => $email,
                'resolved_at' => now(),
            ],
        );

        return $zammadCustomerId;
    }

    public function forgetMapping(Authenticatable $user): void
    {
        ZammadUserMapping::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }
}
