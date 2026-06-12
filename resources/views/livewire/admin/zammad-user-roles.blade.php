<div class="space-y-6">
    <flux:card>
        <flux:heading size="lg" class="mb-2">Zammad-Intranet-Rolle</flux:heading>
        <flux:text class="mb-4">
            Benutzer benötigen diese Zammad-Rolle, damit Tickets per API mit On-Behalf-of erstellt werden können.
        </flux:text>

        @if ($rolesError)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                {{ $rolesError }}
            </flux:callout>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
            <div class="flex-1">
                <flux:select
                    wire:model="zammadIntranetUserRoleId"
                    label="Intranet-Benutzer-Rolle in Zammad"
                    variant="listbox"
                    searchable
                    placeholder="Rolle wählen…"
                >
                    <flux:select.option value="">— Keine Rolle konfiguriert —</flux:select.option>
                    @foreach ($this->zammadRoles as $role)
                        <flux:select.option value="{{ $role['id'] }}">{{ $role['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="refreshRoles" icon="arrow-path" variant="ghost">
                    Rollen laden
                </flux:button>
                <flux:button wire:click="saveRoleSetting" variant="primary" icon="check">
                    Speichern
                </flux:button>
            </div>
        </div>

        @if ($this->configuredRole)
            <flux:text class="mt-3 text-sm text-zinc-500">
                Aktuell: <strong>{{ $this->configuredRole['name'] }}</strong> (ID {{ $this->configuredRole['id'] }})
            </flux:text>
        @endif

        @if ($zammadIntranetUserRoleId !== null && $zammadIntranetUserRoleId !== '')
            <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading size="md">Gruppenberechtigungen der Rolle</flux:heading>
                        <flux:text class="mt-1">
                            Für On-Behalf-of-Tickets benötigen Intranet-Benutzer passende Rechte auf die Zammad-Gruppen.
                        </flux:text>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <flux:button wire:click="refreshGroupPermissions" icon="arrow-path" variant="ghost" size="sm">
                            Neu laden
                        </flux:button>
                        <flux:button wire:click="saveGroupPermissions" icon="check" variant="primary" size="sm">
                            Gruppenrechte speichern
                        </flux:button>
                    </div>
                </div>

                @if ($groupPermissionsError)
                    <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                        {{ $groupPermissionsError }}
                    </flux:callout>
                @endif

                <flux:callout variant="secondary" icon="information-circle" class="mb-4">
                    Es werden nur Zammad-Gruppen angezeigt, die einer Intranet-Ticketkategorie zugeordnet sind. Pro Gruppe sollte mindestens <strong>Erstellen</strong> erlaubt sein.
                </flux:callout>

                @if ($this->groupPermissionRows->isEmpty())
                    <flux:text>Keine Zammad-Gruppen mit Intranet-Ticketkategorie konfiguriert. Hinterlege zuerst unter „Kategorien“ eine Zammad-Gruppe.</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Zammad-Gruppe</flux:table.column>
                            <flux:table.column>Ticketkategorie</flux:table.column>
                            <flux:table.column>Berechtigung</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->groupPermissionRows as $group)
                                <flux:table.row
                                    wire:key="zammad-group-perm-{{ $group['id'] }}"
                                    class="bg-amber-50/80 dark:bg-amber-950/20"
                                >
                                    <flux:table.cell>
                                        <span class="font-medium">{{ $group['name'] }}</span>
                                        <flux:text class="text-xs text-zinc-500">ID {{ $group['id'] }}</flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($group['intranet_categories'] as $categoryLabel)
                                                <flux:badge color="amber" size="sm" icon="tag">
                                                    {{ $categoryLabel }}
                                                </flux:badge>
                                            @endforeach
                                            @if ($group['intranet_inactive'])
                                                <flux:badge color="zinc" size="sm">inaktiv</flux:badge>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:select
                                            wire:model="groupPermissions.{{ $group['id'] }}"
                                            variant="listbox"
                                            placeholder="Keine"
                                            class="min-w-40"
                                        >
                                            <flux:select.option value="">Keine</flux:select.option>
                                            @foreach (\Hwkdo\IntranetAppTickets\Enums\ZammadGroupAccess::cases() as $access)
                                                <flux:select.option value="{{ $access->value }}">{{ $access->label() }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        @endif
    </flux:card>

    <flux:card>
        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">Intranet-Benutzer</flux:heading>
                <flux:text class="mt-1">
                    Übersicht, ob aktive Intranet-Benutzer die konfigurierte Zammad-Rolle haben.
                </flux:text>
            </div>

            <flux:button wire:click="refreshUsers" icon="arrow-path" variant="ghost">
                Status aktualisieren
            </flux:button>
        </div>

        @if ($usersError)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                {{ $usersError }}
            </flux:callout>
        @endif

        @if ($zammadIntranetUserRoleId === null || $zammadIntranetUserRoleId === '')
            <flux:callout variant="warning" icon="information-circle">
                Wähle oben eine Zammad-Rolle aus, um die Übersicht zu aktivieren.
            </flux:callout>
        @else
            <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Suche nach Vorname, Nachname, Benutzername oder E-Mail…"
                    icon="magnifying-glass"
                    class="w-full max-w-md"
                />

                <div class="flex flex-col gap-2">
                    <flux:checkbox wire:model.live="onlyWithoutIntranetRole" label="Nur ohne Intranet-Rolle anzeigen" />
                    <flux:checkbox wire:model.live="onlyWithoutZammadUser" label="Nur ohne Zammad-Benutzer anzeigen" />
                </div>
            </div>

            <flux:text class="mb-4 text-sm text-zinc-500">
                Sucht in Vorname, Nachname, vollem Namen, Benutzername, E-Mail und UPN (username@{{ config('ms-graph-laravel.default_suffix') }}).
            </flux:text>

            <div class="mb-4 flex flex-wrap gap-2">
                <flux:button
                    wire:click="bulkCreateMissingZammadUsers"
                    wire:confirm="Alle Intranet-Benutzer ohne Zammad-Konto anlegen? (berücksichtigt die aktuelle Suche)"
                    icon="user-plus"
                    variant="outline"
                    size="sm"
                >
                    Alle fehlenden Benutzer erstellen
                </flux:button>

                <flux:button
                    wire:click="bulkAssignMissingIntranetRole"
                    wire:confirm="Allen Zammad-Benutzern ohne Intranet-Rolle diese Rolle zuweisen? (berücksichtigt die aktuelle Suche)"
                    icon="users"
                    variant="outline"
                    size="sm"
                >
                    Intranet-Rolle allen Fehlenden zuweisen
                </flux:button>
            </div>

            <flux:table :paginate="$this->users" pagination:scroll-to="#zammad-users-table">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Benutzername</flux:table.column>
                    <flux:table.column>E-Mail</flux:table.column>
                    <flux:table.column>In Zammad</flux:table.column>
                    <flux:table.column>Intranet-Rolle</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row wire:key="zammad-user-{{ $user->id }}">
                            <flux:table.cell>{{ $user->name }}</flux:table.cell>
                            <flux:table.cell>{{ $user->username }}</flux:table.cell>
                            <flux:table.cell>{{ $user->email }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($user->zammad_in_zammad ?? false)
                                    <flux:badge color="green" size="sm">Ja</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Nein</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($user->zammad_has_intranet_role ?? false)
                                    <flux:badge color="green" size="sm">Ja</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Nein</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @if (! ($user->zammad_in_zammad ?? false))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="createZammadUser({{ $user->id }})"
                                            wire:confirm="Zammad-Benutzer für {{ $user->name }} anlegen?"
                                        >
                                            Benutzer erstellen
                                        </flux:button>
                                    @elseif (! ($user->zammad_has_intranet_role ?? false))
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="assignRole({{ $user->id }})"
                                            wire:confirm="Zammad-Rolle diesem Benutzer zuweisen?"
                                        >
                                            Rolle zuweisen
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
