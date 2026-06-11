<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStandortTag extends Model
{
    protected $table = 'intranet_app_ticket_standort_tags';

    protected $fillable = [
        'standort_id',
        'tag',
    ];

    public function standort(): BelongsTo
    {
        $model = config('intranet-app-tickets.standort_model');

        return $this->belongsTo($model, 'standort_id');
    }
}
