<?php

declare(strict_types=1);

use Flux\Flux;
use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Services\ZammadGroupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use function Livewire\Volt\{computed, state};

state([
    'editingId' => null,
    'label' => '',
    'transmission' => 'zammad',
    'zammad_group_id' => null,
    'email' => '',
    'requires_approval' => false,
    'active' => true,
    'approverRoleIds' => [],
    'groupsError' => null,
]);

$categories = computed(fn () => TicketCategory::query()
    ->with('approverRoles')
    ->orderBy('sort_order')
    ->get());

$zammadGroups = computed(function () {
    try {
        return app(ZammadGroupService::class)->listGroups();
    } catch (Throwable $e) {
        $this->groupsError = $e->getMessage();

        return collect();
    }
});

$roles = computed(fn () => Role::query()->orderBy('name')->get());

$edit = function (int $categoryId): void {
    $category = TicketCategory::query()->with('approverRoles')->findOrFail($categoryId);

    $this->editingId = $category->id;
    $this->label = $category->label;
    $this->transmission = $category->transmission->value;
    $this->zammad_group_id = $category->zammad_group_id;
    $this->email = $category->email ?? '';
    $this->requires_approval = $category->requires_approval;
    $this->active = $category->active;
    $this->approverRoleIds = $category->approverRoles->pluck('id')->map(fn ($id) => (string) $id)->all();
};

$refreshGroups = function (): void {
    try {
        app(ZammadGroupService::class)->refreshGroups();
        unset($this->zammadGroups);
        $this->groupsError = null;
        Flux::toast(text: 'Zammad-Gruppen wurden aktualisiert.', variant: 'success');
    } catch (Throwable $e) {
        $this->groupsError = $e->getMessage();
        Flux::toast(text: 'Gruppen konnten nicht geladen werden.', variant: 'danger');
    }
};

$save = function (): void {
    $category = TicketCategory::query()->findOrFail($this->editingId);

    $validated = $this->validate([
        'label' => ['required', 'string', 'max:255'],
        'transmission' => ['required', Rule::in(['zammad', 'email'])],
        'zammad_group_id' => [Rule::requiredIf($this->transmission === 'zammad'), 'nullable', 'integer'],
        'email' => [Rule::requiredIf($this->transmission === 'email'), 'nullable', 'email', 'max:255'],
        'requires_approval' => ['boolean'],
        'active' => ['boolean'],
        'approverRoleIds' => [Rule::requiredIf($this->requires_approval), 'array'],
        'approverRoleIds.*' => ['integer', 'exists:roles,id'],
    ]);

    $category->update([
        'label' => $validated['label'],
        'transmission' => $validated['transmission'],
        'zammad_group_id' => $validated['transmission'] === 'zammad' ? $validated['zammad_group_id'] : null,
        'email' => $validated['transmission'] === 'email' ? $validated['email'] : null,
        'requires_approval' => $validated['requires_approval'],
        'active' => $validated['active'],
    ]);

    $category->approverRoles()->sync(
        $validated['requires_approval'] ? array_map('intval', $validated['approverRoleIds']) : []
    );

    $this->editingId = null;
    unset($this->categories);

    Flux::toast(text: 'Kategorie gespeichert.', variant: 'success');
};

$cancel = function (): void {
    $this->editingId = null;
};

?>

<div class="space-y-6">
  <div class="flex items-center justify-between gap-4">
    <flux:text>Kategorien, Übertragungsweg und Genehmigungsrollen verwalten.</flux:text>
    <flux:button wire:click="refreshGroups" icon="arrow-path" variant="ghost" size="sm">
      Zammad-Gruppen neu laden
    </flux:button>
  </div>

  @if ($groupsError)
    <flux:callout variant="warning" icon="exclamation-triangle">{{ $groupsError }}</flux:callout>
  @endif

  <div class="space-y-3">
    @foreach ($this->categories as $category)
      <flux:card wire:key="category-{{ $category->id }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="space-y-1">
            <flux:heading size="sm">{{ $category->label }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">{{ $category->slug }}</flux:text>
            <div class="flex flex-wrap gap-2">
              <flux:badge size="sm">{{ $category->transmission->label() }}</flux:badge>
              @if ($category->requires_approval)
                <flux:badge size="sm" color="amber">Genehmigung</flux:badge>
              @endif
              @unless ($category->active)
                <flux:badge size="sm" color="zinc">Inaktiv</flux:badge>
              @endunless
            </div>
            <flux:text class="text-sm">
              @if ($category->transmission === TransmissionChannel::Zammad)
                Gruppe: {{ $category->zammad_group_id ?? '—' }}
              @else
                E-Mail: {{ $category->email ?? '—' }}
              @endif
            </flux:text>
          </div>
          <flux:button wire:click="edit({{ $category->id }})" size="sm">Bearbeiten</flux:button>
        </div>

        @if ($editingId === $category->id)
          <div class="mt-6 space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:input wire:model="label" label="Bezeichnung" />

            <flux:select wire:model.live="transmission" label="Übertragungsweg">
              <flux:select.option value="zammad">Zammad API</flux:select.option>
              <flux:select.option value="email">E-Mail</flux:select.option>
            </flux:select>

            @if ($transmission === 'zammad')
              <flux:select wire:model="zammad_group_id" label="Zammad-Gruppe" placeholder="Gruppe wählen">
                @foreach ($this->zammadGroups as $group)
                  <flux:select.option value="{{ $group['id'] }}">{{ $group['name'] }}</flux:select.option>
                @endforeach
              </flux:select>
            @else
              <flux:input wire:model="email" type="email" label="E-Mail-Adresse" />
            @endif

            <flux:checkbox wire:model.live="requires_approval" label="Genehmigung erforderlich" />
            <flux:checkbox wire:model="active" label="Aktiv" />

            @if ($requires_approval)
              <flux:select wire:model="approverRoleIds" label="Genehmigungs-Rollen" variant="listbox" multiple placeholder="Rollen wählen">
                @foreach ($this->roles as $role)
                  <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                @endforeach
              </flux:select>
            @endif

            <div class="flex gap-2">
              <flux:button wire:click="save" variant="primary">Speichern</flux:button>
              <flux:button wire:click="cancel" variant="ghost">Abbrechen</flux:button>
            </div>
          </div>
        @endif
      </flux:card>
    @endforeach
  </div>
</div>
