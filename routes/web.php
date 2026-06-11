<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Http\Controllers\TicketAttachmentController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::webhooks('webhooks/zammad', 'tickets-zammad');

Route::middleware(['web', 'auth', 'can:see-app-tickets'])->group(function () {
    Volt::route('apps/tickets', 'apps.tickets.index')->name('apps.tickets.index');
    Volt::route('apps/tickets/settings/user', 'apps.tickets.settings.user')->name('apps.tickets.settings.user');
    Volt::route('apps/tickets/info', 'apps.tickets.info')->name('apps.tickets.info');
    Volt::route('apps/tickets/{ticketId}', 'apps.tickets.show')
        ->whereNumber('ticketId')
        ->name('apps.tickets.show');

    Route::get(
        'apps/tickets/{ticketId}/articles/{articleId}/attachments/{attachmentId}',
        TicketAttachmentController::class,
    )->whereNumber(['ticketId', 'articleId', 'attachmentId'])
        ->name('apps.tickets.attachments.download');
});

Route::middleware(['web', 'auth', 'can:manage-app-tickets'])->group(function () {
    Volt::route('apps/tickets/admin', 'apps.tickets.admin.index')->name('apps.tickets.admin.index');
    Volt::route('apps/tickets/webhooks', 'apps.tickets.webhooks.index')->name('apps.tickets.webhooks.index');
    Volt::route('apps/tickets/webhooks/{id}/payload', 'apps.tickets.webhooks.show-payload')->name('apps.tickets.webhooks.show-payload');
});
