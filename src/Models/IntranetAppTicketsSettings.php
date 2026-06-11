<?php

namespace Hwkdo\IntranetAppTickets\Models;

use Hwkdo\IntranetAppTickets\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;

class IntranetAppTicketsSettings extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): ?IntranetAppTicketsSettings
    {
        return self::orderBy('version', 'desc')->first();
    }
}
