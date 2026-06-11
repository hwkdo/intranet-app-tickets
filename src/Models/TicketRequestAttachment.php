<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketRequestAttachment extends Model
{
    protected $table = 'intranet_app_ticket_request_attachments';

    protected $fillable = [
        'ticket_request_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function ticketRequest(): BelongsTo
    {
        return $this->belongsTo(TicketRequest::class, 'ticket_request_id');
    }

    public function fullPath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public function contents(): string
    {
        return Storage::disk($this->disk)->get($this->path);
    }
}
