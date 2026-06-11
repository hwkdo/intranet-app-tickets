# Intranet App Tickets

Zammad-Integration für das Intranet: Benutzer können ihre Support-Tickets einsehen und beantworten. Agenten arbeiten weiterhin im Zammad-UI.

## Installation

```bash
composer require hwkdo/intranet-app-tickets
php artisan migrate
php artisan intranet-app:sync-settings
php artisan intranet-app:sync-permissions --all
```

## Umgebungsvariablen

```env
ZAMMAD_URL=https://ticket.hwkdo.com
ZAMMAD_HTTP_TOKEN=your-api-token
WEBHOOK_ZAMMAD_SECRET=your-webhook-hmac-secret
```

Optional:

```env
ZAMMAD_DEBUG=false
INTRANET_APP_TICKETS_USER_MODEL=App\Models\User
```

## Zammad Webhook einrichten

1. In Zammad unter **Verwalten → Webhooks** einen neuen Webhook anlegen.
2. Endpoint: `{APP_URL}/webhooks/zammad`
3. HMAC-Token setzen (gleicher Wert wie `WEBHOOK_ZAMMAD_SECRET`).
4. Unter **Verwalten → Trigger** Webhook-Trigger anlegen, z. B.:
   - **Agent-Antworten:** Artikel erstellt, Sichtbarkeit = öffentlich, Sender = Agent
   - **Statusänderungen:** Ticket aktualisiert, z. B. Status = geschlossen (ohne Artikel im Payload)
   - Aktion jeweils: Webhook auslösen

Optional `INTRANET_APP_TICKETS_WEBHOOK_NOTIFY_STATES=closed,open` setzen, um nur bestimmte Status zu benachrichtigen (Standard: alle).

## Webhook-Debugging

Eingegangene Webhooks (gespeichert nach gültiger Signatur) unter:

- `/apps/tickets/webhooks` (Permission `manage-app-tickets`)

Zusätzlich in der allgemeinen Admin-Webhook-Übersicht (`/admin/webhooks`), sofern der Eintrag gespeichert wurde.

**Wichtig:** Der Webhook-Job läuft über die Queue (`QUEUE_CONNECTION=redis`). Horizon bzw. Queue-Worker müssen laufen, sonst bleibt der Eintrag in `webhook_calls` ohne Benutzer-Benachrichtigung.

## Funktionen

- Ticketliste (offen / geschlossen / alle) per Zammad API
- Ticketdetail mit öffentlichem Verlauf und Bearbeiter-Info
- Antworten als Kunde (`setOnBehalfOfUser`)
- Anhang-Download über die App
- Webhook-Benachrichtigungen bei Agent-Antworten (Reverb + Task-Badge)
- Webhook-Übersicht mit Verarbeitungsstatus und Live-Updates per Reverb/Echo

## Berechtigungen

- `see-app-tickets` — App nutzen
- `manage-app-tickets` — Admin-Bereich
