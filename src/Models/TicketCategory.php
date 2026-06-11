<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Models;

use Hwkdo\IntranetAppTickets\Enums\TicketFormType;
use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class TicketCategory extends Model
{
    protected $table = 'intranet_app_ticket_categories';

    protected $fillable = [
        'slug',
        'label',
        'form',
        'transmission',
        'zammad_group_id',
        'email',
        'requires_approval',
        'active',
        'sort_order',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'form' => TicketFormType::class,
            'transmission' => TransmissionChannel::class,
            'requires_approval' => 'boolean',
            'active' => 'boolean',
            'zammad_group_id' => 'integer',
            'legacy_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function approverRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'intranet_app_ticket_category_role',
            'ticket_category_id',
            'role_id',
        )->withTimestamps();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(TicketRequest::class, 'ticket_category_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isConfiguredForDispatch(): bool
    {
        if ($this->transmission === TransmissionChannel::Zammad) {
            return $this->zammad_group_id !== null;
        }

        return $this->email !== null && $this->email !== '';
    }
}
