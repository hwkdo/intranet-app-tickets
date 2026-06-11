<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tickets-zammad-webhooks', function ($user): bool {
    return $user->can('manage-app-tickets');
});
