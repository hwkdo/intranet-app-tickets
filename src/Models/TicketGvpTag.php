<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketGvpTag extends Model
{
    protected $table = 'intranet_app_ticket_gvp_tags';

    protected $fillable = [
        'gvp_id',
        'tag',
    ];

    public function gvp(): BelongsTo
    {
        $model = config('intranet-app-tickets.gvp_model');

        return $this->belongsTo($model, 'gvp_id');
    }
}
