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

## Ticket-Kategorien einrichten

Nach der Migration die Standard-Kategorien seeden:

```bash
php artisan intranet-app-tickets:seed-categories
```

Im Admin-Bereich (`/apps/tickets/admin` → Tab **Kategorien**) pro Kategorie den Übertragungsweg konfigurieren:

- **Zammad API:** Zammad-Gruppe aus Dropdown wählen (Gruppen werden per API geladen)
- **E-Mail:** Zieladresse eintragen (Standard für Moodle)
- Optional: Genehmigung aktivieren und Genehmigungs-Rollen zuweisen

## Funktionen

- Ticketliste (offen / geschlossen / alle) per Zammad API + eigene Anfragen („Zur Genehmigung“)
- Tickets erstellen mit kategoriespezifischen Flux-Formularen
- Genehmigungsworkflow mit Rollen pro Kategorie
- Übertragung per Zammad API oder E-Mail (konfigurierbar pro Kategorie)
- Ticketdetail mit öffentlichem Verlauf und Bearbeiter-Info
- Ticket-Erstellung und Antworten als Kunde (`setOnBehalfOfUser`)
- Admin: Zammad-Intranet-Rolle, Gruppenrechte und Benutzerübersicht (`/apps/tickets/admin` → Tab **Zammad-Benutzer**)
- Automatische Zammad-Benutzeranlage beim ersten Ticket (Intranet-Daten → Zammad, inkl. Intranet-Rolle)
- Anhang-Download über die App
- Webhook-Benachrichtigungen bei Agent-Antworten (Reverb + Task-Badge)
- Webhook-Übersicht mit Verarbeitungsstatus und Live-Updates per Reverb/Echo

## Zammad API-Token (Berechtigungen)

| Funktion | Zammad-Permission |
|----------|-------------------|
| Tickets erstellen / lesen | `ticket.agent` |
| Benutzer automatisch anlegen + Rolle zuweisen | `admin.user` |
| Gruppenrechte der Intranet-Rolle pflegen | `admin.role` |

Voraussetzung für Auto-Provisioning: Im Admin-Tab **Zammad-Benutzer** muss eine **Intranet-Benutzer-Rolle** konfiguriert sein (inkl. Gruppenrechte für die Ticket-Kategorien).

## Berechtigungen

- `see-app-tickets` — App nutzen
- `manage-app-tickets` — Admin-Bereich

## Tests

Alle Tests liegen im Package unter `tests/` und werden über die Hauptanwendung ausgeführt:

```bash
php artisan test --compact packages/intranet-app-tickets/tests
```

Einzelne Suite:

```bash
php artisan test --compact packages/intranet-app-tickets/tests/Unit
php artisan test --compact packages/intranet-app-tickets/tests/Feature
```
