<?php

declare(strict_types=1);

return [
    'roles' => [
        'admin' => [
            'name' => 'App-Tickets-Admin',
            'permissions' => [
                'see-app-tickets',
                'manage-app-tickets',
            ],
        ],
        'user' => [
            'name' => 'App-Tickets-Benutzer',
            'permissions' => [
                'see-app-tickets',
            ],
            'all_users' => true,
        ],
    ],

    'user_model' => env('INTRANET_APP_TICKETS_USER_MODEL', 'App\\Models\\User'),

    'standort_model' => env('INTRANET_APP_TICKETS_STANDORT_MODEL', 'App\\Models\\Standort'),

    'gvp_model' => env('INTRANET_APP_TICKETS_GVP_MODEL', 'App\\Models\\Gvp'),

    'zammad' => [
        'url' => env('ZAMMAD_URL', 'https://ticket.hwkdo.com'),
        'http_token' => env('ZAMMAD_HTTP_TOKEN'),
        'debug' => env('ZAMMAD_DEBUG', false),
    ],

    'webhook' => [
        'secret' => env('WEBHOOK_ZAMMAD_SECRET'),
        /**
         * Ticket states that trigger a customer notification (Zammad "state" value, e.g. "closed").
         * null = all status-change webhooks without article are notified.
         *
         * @var list<string>|null
         */
        'notify_states' => env('INTRANET_APP_TICKETS_WEBHOOK_NOTIFY_STATES')
            ? array_values(array_filter(array_map('trim', explode(',', (string) env('INTRANET_APP_TICKETS_WEBHOOK_NOTIFY_STATES')))))
            : null,
    ],

    /*
     * Teams-Bot Integration: Erstellung von Tickets aus Microsoft-Teams-Nachrichten.
     * default_category_slug bestimmt die Kategorie, in der Bot-Tickets angelegt werden.
     * Kategorien mit Genehmigungspflicht werden aus dem Bot heraus nicht bedient.
     */
    'teams_bot' => [
        'default_category_slug' => env('INTRANET_APP_TICKETS_TEAMS_DEFAULT_CATEGORY', 'it-support'),
        'ai' => [
            'langdock_model_fallback' => env('INTRANET_APP_TICKETS_TEAMS_AI_LANGDOCK_MODEL', 'gpt-4o'),
            'openwebui_model_fallback' => env('INTRANET_APP_TICKETS_TEAMS_AI_OPENWEBUI_MODEL', 'gpt-oss:20b'),
        ],
    ],

    'closed_state_ids' => [4, 5],

    'list_per_page' => (int) env('INTRANET_APP_TICKETS_LIST_PER_PAGE', 15),
];
