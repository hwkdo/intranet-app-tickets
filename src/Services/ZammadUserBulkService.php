<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Data\ZammadBulkActionResult;
use Hwkdo\IntranetAppTickets\Support\IntranetUserSearchFilter;
use Hwkdo\IntranetAppTickets\Support\ZammadUserProfileMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

class ZammadUserBulkService
{
    public function __construct(
        private readonly ZammadUserResolver $userResolver,
        private readonly ZammadUserRoleService $userRoleService,
        private readonly ZammadUserProfileMapper $profileMapper,
        private readonly IntranetUserSearchFilter $searchFilter,
    ) {}

    public function createAllMissingUsers(?string $search = null): ZammadBulkActionResult
    {
        $roleMap = $this->userRoleService->getEmailToRoleIdsMap();
        $result = new ZammadBulkActionResult;

        $this->eachActiveUser($search, function (Model $user) use (&$roleMap, &$result): void {
            $result = $this->incrementProcessed($result);
            $email = $this->profileMapper->resolveEmail($user);

            if ($email === null) {
                $result = $this->incrementSkipped($result, 'keine_e_mail');

                return;
            }

            if ($roleMap->has(mb_strtolower($email))) {
                $result = $this->incrementSkipped($result, 'bereits_in_zammad');

                return;
            }

            try {
                $this->userResolver->provisionCustomer($user);
                $roleMap = $this->userRoleService->getEmailToRoleIdsMap();
                $result = $this->incrementSucceeded($result);
            } catch (Throwable $exception) {
                $result = $this->incrementFailed($result, $this->userLabel($user), $exception);
            }
        });

        return $result;
    }

    public function assignIntranetRoleToAllMissing(int $roleId, ?string $search = null): ZammadBulkActionResult
    {
        if ($roleId <= 0) {
            throw new RuntimeException('Keine gültige Zammad-Intranet-Rolle konfiguriert (role_id='.$roleId.').');
        }

        $roleMap = $this->userRoleService->getEmailToRoleIdsMap();
        $result = new ZammadBulkActionResult;

        $this->eachActiveUser($search, function (Model $user) use ($roleId, &$roleMap, &$result): void {
            $result = $this->incrementProcessed($result);
            $email = $this->profileMapper->resolveEmail($user);

            if ($email === null) {
                $result = $this->incrementSkipped($result, 'keine_e_mail');

                return;
            }

            $normalizedEmail = mb_strtolower($email);

            if (! $roleMap->has($normalizedEmail)) {
                $result = $this->incrementSkipped($result, 'nicht_in_zammad');

                return;
            }

            if ($this->userRoleService->emailHasRole($email, $roleId, $roleMap)) {
                $result = $this->incrementSkipped($result, 'rolle_bereits_vorhanden');

                return;
            }

            try {
                $this->userRoleService->assignRoleToUser($user, $roleId, forgetRoleMapCache: false);
                $roleIds = $roleMap->get($normalizedEmail, []);
                $roleIds[] = $roleId;
                $roleMap->put($normalizedEmail, array_values(array_unique($roleIds)));
                $result = $this->incrementSucceeded($result);
            } catch (Throwable $exception) {
                $result = $this->incrementFailed($result, $this->userLabel($user), $exception);
            }
        });

        $this->userRoleService->forgetCache();

        return $result;
    }

    private function eachActiveUser(?string $search, callable $callback): void
    {
        $userModel = config('intranet-app-tickets.user_model');

        /** @var Builder<Model> $query */
        $query = $userModel::query()
            ->aktiv()
            ->orderBy('id');

        $this->searchFilter->apply($query, $search ?? '');

        $query->chunkById(100, function ($users) use ($callback): void {
            foreach ($users as $user) {
                $callback($user);
            }
        });
    }

    private function userLabel(Model $user): string
    {
        $name = trim((string) ($user->getAttribute('vorname').' '.$user->getAttribute('nachname')));

        if ($name !== '') {
            return $name;
        }

        return (string) ($user->getAttribute('username') ?? 'Benutzer #'.$user->getKey());
    }

    private function incrementProcessed(ZammadBulkActionResult $result): ZammadBulkActionResult
    {
        return new ZammadBulkActionResult(
            succeeded: $result->succeeded,
            failed: $result->failed,
            skipped: $result->skipped,
            errors: $result->errors,
            processed: $result->processed + 1,
            skippedReasons: $result->skippedReasons,
        );
    }

    private function incrementSucceeded(ZammadBulkActionResult $result): ZammadBulkActionResult
    {
        return new ZammadBulkActionResult(
            succeeded: $result->succeeded + 1,
            failed: $result->failed,
            skipped: $result->skipped,
            errors: $result->errors,
            processed: $result->processed,
            skippedReasons: $result->skippedReasons,
        );
    }

    private function incrementSkipped(ZammadBulkActionResult $result, string $reason): ZammadBulkActionResult
    {
        $skippedReasons = $result->skippedReasons;
        $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;

        return new ZammadBulkActionResult(
            succeeded: $result->succeeded,
            failed: $result->failed,
            skipped: $result->skipped + 1,
            errors: $result->errors,
            processed: $result->processed,
            skippedReasons: $skippedReasons,
        );
    }

    private function incrementFailed(ZammadBulkActionResult $result, string $label, Throwable $exception): ZammadBulkActionResult
    {
        $errors = $result->errors;
        $errors[] = $label.': '.$exception->getMessage();

        return new ZammadBulkActionResult(
            succeeded: $result->succeeded,
            failed: $result->failed + 1,
            skipped: $result->skipped,
            errors: $errors,
            processed: $result->processed,
            skippedReasons: $result->skippedReasons,
        );
    }
}
