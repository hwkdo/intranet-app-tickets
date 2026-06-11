<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZammadUserMapping extends Model
{
    protected $table = 'intranet_app_tickets_user_mappings';

    protected $fillable = [
        'user_id',
        'zammad_customer_id',
        'zammad_email',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        $userModel = config('intranet-app-tickets.user_model');

        return $this->belongsTo($userModel, 'user_id');
    }
}
