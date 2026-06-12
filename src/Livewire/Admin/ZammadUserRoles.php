<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Livewire\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Enums\ZammadGroupAccess;
use Hwkdo\IntranetAppTickets\Services\TicketsAppSettingsStore;
use Hwkdo\IntranetAppTickets\Services\ZammadGroupService;
use Hwkdo\IntranetAppTickets\Services\ZammadRoleService;
use Hwkdo\IntranetAppTickets\Data\ZammadBulkActionResult;
use Hwkdo\IntranetAppTickets\Services\ZammadUserBulkService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Hwkdo\IntranetAppTickets\Services\ZammadUserRoleService;
use Hwkdo\IntranetAppTickets\Support\IntranetUserSearchFilter;
use Hwkdo\IntranetAppTickets\Support\ZammadIntranetGroupMapper;
use Hwkdo\IntranetAppTickets\Support\ZammadUserProfileMapper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Isolate;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Isolate]
class ZammadUserRoles extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $onlyWithoutIntranetRole = true;

    public bool $onlyWithoutZammadUser = false;

    public int|string|null $zammadIntranetUserRoleId = null;

    public ?string $rolesError = null;

    public ?string $usersError = null;

    public ?string $groupPermissionsError = null;

    /** @var array<string, string> */
    public array $groupPermissions = [];

    protected string $pageName = 'usersPage';

    public function mount(): void
    {
        $this->zammadIntranetUserRoleId = app(TicketsAppSettingsStore::class)->zammadIntranetUserRoleId();
        $this->loadGroupPermissions();
    }

    public function updatedZammadIntranetUserRoleId(): void
    {
        $this->loadGroupPermissions();
        unset($this->groupPermissionRows, $this->configuredRole, $this->users);
        $this->resetPage($this->pageName);
    }

    public function updatedSearch(): void
    {
        $this->resetPage($this->pageName);
    }

    public function updatedOnlyWithoutIntranetRole(): void
    {
        $this->resetPage($this->pageName);
    }

    public function updatedOnlyWithoutZammadUser(): void
    {
        $this->resetPage($this->pageName);
    }

    #[Computed]
    public function zammadRoles()
    {
        try {
            $this->rolesError = null;

            return app(ZammadRoleService::class)->listRoles();
        } catch (Throwable $exception) {
            $this->rolesError = $exception->getMessage();

            return collect();
        }
    }

    #[Computed]
    public function configuredRole(): ?array
    {
        if ($this->zammadIntranetUserRoleId === null || $this->zammadIntranetUserRoleId === '') {
            return null;
        }

        return $this->zammadRoles->firstWhere('id', (int) $this->zammadIntranetUserRoleId);
    }

    #[Computed]
    public function groupPermissionRows()
    {
        if ($this->zammadIntranetUserRoleId === null || $this->zammadIntranetUserRoleId === '') {
            return collect();
        }

        $categoriesByGroup = app(ZammadIntranetGroupMapper::class)->categoriesByGroupId();
        $groups = app(ZammadGroupService::class)->listGroups();

        return $groups
            ->filter(fn (array $group): bool => $categoriesByGroup->has((string) $group['id']))
            ->map(function (array $group) use ($categoriesByGroup): array {
                $groupId = (string) $group['id'];
                $categories = $categoriesByGroup->get($groupId, collect());

                return [
                    'id' => (int) $group['id'],
                    'name' => $group['name'],
                    'intranet_categories' => $categories->pluck('label')->all(),
                    'intranet_inactive' => $categories->every(fn ($category) => ! $category->active),
                ];
            })
            ->sortBy(fn (array $row): string => mb_strtolower($row['name']))
            ->values();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $userModel = config('intranet-app-tickets.user_model');
        $roleId = $this->zammadIntranetUserRoleId !== null && $this->zammadIntranetUserRoleId !== ''
            ? (int) $this->zammadIntranetUserRoleId
            : null;

        $query = $userModel::query()
            ->aktiv()
            ->orderBy('nachname')
            ->orderBy('vorname');

        app(IntranetUserSearchFilter::class)->apply($query, $this->search);

        $roleMap = collect();
        $roleService = app(ZammadUserRoleService::class);
        $profileMapper = app(ZammadUserProfileMapper::class);

        if ($roleId !== null) {
            try {
                $roleMap = app(ZammadUserRoleService::class)->getEmailToRoleIdsMap();
                $this->usersError = null;
            } catch (Throwable $exception) {
                $this->usersError = $exception->getMessage();
                $roleMap = collect();
            }
        }

        if ($roleId !== null && ($this->onlyWithoutIntranetRole || $this->onlyWithoutZammadUser)) {
            $onlyWithoutIntranetRole = $this->onlyWithoutIntranetRole;
            $onlyWithoutZammadUser = $this->onlyWithoutZammadUser;

            $matchingIds = (clone $query)
                ->get(['id', 'email', 'username'])
                ->filter(function (object $user) use (
                    $roleId,
                    $roleMap,
                    $roleService,
                    $profileMapper,
                    $onlyWithoutIntranetRole,
                    $onlyWithoutZammadUser,
                ): bool {
                    $email = $profileMapper->resolveEmail($user);
                    $normalizedEmail = mb_strtolower(trim($email ?? ''));

                    if ($onlyWithoutZammadUser) {
                        $inZammad = $normalizedEmail !== '' && $roleMap->has($normalizedEmail);

                        if ($inZammad) {
                            return false;
                        }
                    }

                    if ($onlyWithoutIntranetRole) {
                        if ($email === null) {
                            return true;
                        }

                        return ! $roleService->emailHasRole($email, $roleId, $roleMap);
                    }

                    return true;
                })
                ->pluck('id');

            $query->whereIn('id', $matchingIds);
        }

        $paginator = $query->paginate(
            25,
            ['*'],
            $this->pageName,
            $this->getPage($this->pageName),
        );

        if ($roleId === null) {
            return $paginator;
        }

        return $paginator->through(function (object $user) use ($roleId, $roleMap, $roleService, $profileMapper): object {
            $email = $profileMapper->resolveEmail($user) ?? '';
            $normalizedEmail = mb_strtolower(trim($email));
            $inZammad = $normalizedEmail !== '' && $roleMap->has($normalizedEmail);

            $user->zammad_in_zammad = $inZammad;
            $user->zammad_has_intranet_role = $email !== '' && $roleService->emailHasRole($email, $roleId, $roleMap);

            return $user;
        });
    }

    public function saveRoleSetting(): void
    {
        if ($this->zammadIntranetUserRoleId === '' || $this->zammadIntranetUserRoleId === null) {
            $roleId = null;
        } else {
            $validated = $this->validate([
                'zammadIntranetUserRoleId' => ['required', 'integer', 'min:1'],
            ]);
            $roleId = (int) $validated['zammadIntranetUserRoleId'];
        }

        app(TicketsAppSettingsStore::class)->updateZammadIntranetUserRoleId($roleId);

        $this->loadGroupPermissions();
        unset($this->configuredRole, $this->groupPermissionRows, $this->users);

        Flux::toast(text: 'Zammad-Intranet-Rolle gespeichert.', variant: 'success');
    }

    public function saveGroupPermissions(): void
    {
        if ($this->zammadIntranetUserRoleId === null || $this->zammadIntranetUserRoleId === '') {
            Flux::toast(text: 'Bitte zuerst eine Zammad-Intranet-Rolle auswählen.', variant: 'warning');

            return;
        }

        $allowedAccess = collect(ZammadGroupAccess::cases())->map(fn (ZammadGroupAccess $access) => $access->value)->all();

        $validated = $this->validate([
            'groupPermissions' => ['array'],
            'groupPermissions.*' => ['nullable', 'string', Rule::in(array_merge([''], $allowedAccess))],
        ]);

        try {
            app(ZammadRoleService::class)->updateGroupPermissions(
                (int) $this->zammadIntranetUserRoleId,
                $validated['groupPermissions'],
            );
            $this->loadGroupPermissions();
            unset($this->groupPermissionRows);
            $this->groupPermissionsError = null;
            Flux::toast(text: 'Gruppenberechtigungen wurden in Zammad gespeichert.', variant: 'success');
        } catch (Throwable $exception) {
            $this->groupPermissionsError = $exception->getMessage();
            Flux::toast(text: $exception->getMessage(), variant: 'danger');
        }
    }

    public function refreshGroupPermissions(): void
    {
        try {
            app(ZammadGroupService::class)->refreshGroups();
            $this->loadGroupPermissions();
            unset($this->groupPermissionRows);
            $this->groupPermissionsError = null;
            Flux::toast(text: 'Gruppenberechtigungen wurden neu geladen.', variant: 'success');
        } catch (Throwable $exception) {
            $this->groupPermissionsError = $exception->getMessage();
            Flux::toast(text: 'Gruppenberechtigungen konnten nicht geladen werden.', variant: 'danger');
        }
    }

    public function refreshRoles(): void
    {
        try {
            app(ZammadRoleService::class)->refreshRoles();
            unset($this->zammadRoles);
            $this->rolesError = null;
            Flux::toast(text: 'Zammad-Rollen wurden aktualisiert.', variant: 'success');
        } catch (Throwable $exception) {
            $this->rolesError = $exception->getMessage();
            Flux::toast(text: 'Rollen konnten nicht geladen werden.', variant: 'danger');
        }
    }

    public function refreshUsers(): void
    {
        try {
            app(ZammadUserRoleService::class)->forgetCache();
            unset($this->users);
            $this->usersError = null;
            Flux::toast(text: 'Zammad-Benutzerdaten wurden aktualisiert.', variant: 'success');
        } catch (Throwable $exception) {
            $this->usersError = $exception->getMessage();
            Flux::toast(text: 'Benutzerdaten konnten nicht aktualisiert werden.', variant: 'danger');
        }
    }

    public function bulkCreateMissingZammadUsers(): void
    {
        if (! $this->ensureIntranetRoleConfigured()) {
            return;
        }

        try {
            $result = app(ZammadUserBulkService::class)->createAllMissingUsers($this->search);
            unset($this->users);
            $this->toastBulkResult('Zammad-Benutzer erstellt', $result);
        } catch (Throwable $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');
        }
    }

    public function bulkAssignMissingIntranetRole(): void
    {
        $roleId = $this->configuredIntranetRoleId();

        if ($roleId === null) {
            Flux::toast(text: 'Bitte zuerst eine Zammad-Intranet-Rolle auswählen.', variant: 'warning');

            return;
        }

        try {
            $result = app(ZammadUserBulkService::class)->assignIntranetRoleToAllMissing($roleId, $this->search);
            unset($this->users);
            $this->toastBulkResult('Intranet-Rolle zugewiesen', $result);
        } catch (Throwable $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');
        }
    }

    public function createZammadUser(int $userId): void
    {
        $userModel = config('intranet-app-tickets.user_model');
        $user = $userModel::query()->findOrFail($userId);

        try {
            app(ZammadUserResolver::class)->provisionCustomer($user);
            unset($this->users);
            Flux::toast(text: 'Zammad-Benutzer wurde angelegt.', variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');
        }
    }

    public function assignRole(int $userId): void
    {
        $roleId = $this->configuredIntranetRoleId();

        if ($roleId === null) {
            Flux::toast(text: 'Bitte zuerst eine Zammad-Intranet-Rolle auswählen.', variant: 'warning');

            return;
        }

        $userModel = config('intranet-app-tickets.user_model');
        $user = $userModel::query()->findOrFail($userId);

        try {
            app(ZammadUserRoleService::class)->assignRoleToUser($user, $roleId);
            unset($this->users);
            Flux::toast(text: 'Zammad-Rolle wurde zugewiesen.', variant: 'success');
        } catch (Throwable $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');
        }
    }

    private function configuredIntranetRoleId(): ?int
    {
        if ($this->zammadIntranetUserRoleId === null || $this->zammadIntranetUserRoleId === '') {
            return null;
        }

        return (int) $this->zammadIntranetUserRoleId;
    }

    private function ensureIntranetRoleConfigured(): bool
    {
        if ($this->configuredIntranetRoleId() !== null) {
            return true;
        }

        Flux::toast(text: 'Bitte zuerst eine Zammad-Intranet-Rolle auswählen.', variant: 'warning');

        return false;
    }

    private function toastBulkResult(string $actionLabel, ZammadBulkActionResult $result): void
    {
        $summary = sprintf(
            '%s: %d erfolgreich, %d fehlgeschlagen, %d übersprungen.',
            $actionLabel,
            $result->succeeded,
            $result->failed,
            $result->skipped,
        );

        if ($result->hasFailures()) {
            $details = collect($result->errors)->take(3)->implode(' ');

            Flux::toast(
                text: $summary.' '.$details,
                variant: 'warning',
            );

            return;
        }

        Flux::toast(text: $summary, variant: 'success');
    }

    private function loadGroupPermissions(): void
    {
        if ($this->zammadIntranetUserRoleId === null || $this->zammadIntranetUserRoleId === '') {
            $this->groupPermissions = [];
            $this->groupPermissionsError = null;

            return;
        }

        try {
            $role = app(ZammadRoleService::class)->getRole((int) $this->zammadIntranetUserRoleId);
            $existing = app(ZammadRoleService::class)->parseGroupPermissionsFromRole($role);
            $intranetGroupIds = app(ZammadIntranetGroupMapper::class)->categoriesByGroupId()->keys();
            $permissions = [];

            foreach ($intranetGroupIds as $groupId) {
                $permissions[(string) $groupId] = $existing[(string) $groupId] ?? '';
            }

            $this->groupPermissions = $permissions;
            $this->groupPermissionsError = null;
        } catch (Throwable $exception) {
            $this->groupPermissions = [];
            $this->groupPermissionsError = $exception->getMessage();
        }
    }

    public function render(): View
    {
        return view('intranet-app-tickets::livewire.admin.zammad-user-roles');
    }
}
