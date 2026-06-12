<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Data\ZammadBulkActionResult;
use Hwkdo\IntranetAppTickets\Support\IntranetUserSearchFilter;
use Hwkdo\IntranetAppTickets\Support\ZammadUserProfileMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            $email = $this->profileMapper->resolveEmail($user);

            if ($email === null) {
                $result = $this->incrementSkipped($result);

                return;
            }

            if ($roleMap->has(mb_strtolower($email))) {
                $result = $this->incrementSkipped($result);

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
        $roleMap = $this->userRoleService->getEmailToRoleIdsMap();
        $result = new ZammadBulkActionResult;

        $this->eachActiveUser($search, function (Model $user) use ($roleId, &$roleMap, &$result): void {
            $email = $this->profileMapper->resolveEmail($user);

            if ($email === null) {
                $result = $this->incrementSkipped($result);

                return;
            }

            $normalizedEmail = mb_strtolower($email);

            if (! $roleMap->has($normalizedEmail)) {
                $result = $this->incrementSkipped($result);

                return;
            }

            if ($this->userRoleService->emailHasRole($email, $roleId, $roleMap)) {
                $result = $this->incrementSkipped($result);

                return;
            }

            try {
                $this->userRoleService->assignRoleToUser($user, $roleId);
                $roleMap = $this->userRoleService->getEmailToRoleIdsMap();
                $result = $this->incrementSucceeded($result);
            } catch (Throwable $exception) {
                $result = $this->incrementFailed($result, $this->userLabel($user), $exception);
            }
        });

        return $result;
    }

    private function eachActiveUser(?string $search, callable $callback): void
    {
        $userModel = config('intranet-app-tickets.user_model');

        /** @var Builder<Model> $query */
        $query = $userModel::query()
            ->aktiv()
            ->orderBy('nachname')
            ->orderBy('vorname');

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

    private function incrementSucceeded(ZammadBulkActionResult $result): ZammadBulkActionResult
    {
        return new ZammadBulkActionResult(
            succeeded: $result->succeeded + 1,
            failed: $result->failed,
            skipped: $result->skipped,
            errors: $result->errors,
        );
    }

    private function incrementSkipped(ZammadBulkActionResult $result): ZammadBulkActionResult
    {
        return new ZammadBulkActionResult(
            succeeded: $result->succeeded,
            failed: $result->failed,
            skipped: $result->skipped + 1,
            errors: $result->errors,
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
        );
    }
}
