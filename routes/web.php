<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Http\Controllers\TicketAttachmentController;
use Hwkdo\IntranetAppTickets\Http\Controllers\PruefungTourDemoController;
use Hwkdo\IntranetAppTickets\Http\Controllers\TicketTourDemoController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::webhooks('webhooks/zammad', 'tickets-zammad');


Route::middleware(['web', 'auth', 'can:see-app-tickets'])->group(function () {
    Volt::route('apps/tickets', 'apps.tickets.index')->name('apps.tickets.index');

    Volt::route('apps/tickets/chat', 'apps.tickets.chat')
        ->middleware('can:allow_ai_usage')
        ->name('apps.tickets.chat');

    Volt::route('apps/tickets/create', 'apps.tickets.create.index')->name('apps.tickets.create.index');

    Volt::route('apps/tickets/create/{category}', 'apps.tickets.create.form')->name('apps.tickets.create.form');

    Volt::route('apps/tickets/approvals', 'apps.tickets.approvals.index')->name('apps.tickets.approvals.index');

    Volt::route('apps/tickets/approvals/{ticketRequest}', 'apps.tickets.approvals.show')->name('apps.tickets.approvals.show');

    Volt::route('apps/tickets/requests/{ticketRequest}', 'apps.tickets.requests.show')->name('apps.tickets.requests.show');
    Volt::route('apps/tickets/info', 'apps.tickets.info')->name('apps.tickets.info');

    Volt::route('apps/tickets/manual', 'apps.tickets.manual')->name('apps.tickets.manual');


    Route::post('apps/tickets/tour/demo/enable', [TicketTourDemoController::class, 'enable'])
        ->name('apps.tickets.tour.demo.enable');

    Route::post('apps/tickets/tour/demo/disable', [TicketTourDemoController::class, 'disable'])
        ->name('apps.tickets.tour.demo.disable');

    Route::post('apps/tickets/tour/demo/simulate-update', [TicketTourDemoController::class, 'simulateUpdate'])
        ->name('apps.tickets.tour.demo.simulate-update');

    Route::post('apps/tickets/tour/pruefung-demo/enable', [PruefungTourDemoController::class, 'enable'])
        ->name('apps.tickets.tour.pruefung-demo.enable');

    Route::post('apps/tickets/tour/pruefung-demo/disable', [PruefungTourDemoController::class, 'disable'])
        ->name('apps.tickets.tour.pruefung-demo.disable');


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
