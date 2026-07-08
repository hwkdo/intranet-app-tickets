<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Ai;

use Illuminate\Support\Str;
use RuntimeException;

final class TeamsTicketAiPrompt
{
    public const SYSTEM = <<<'PROMPT'
Du formulierst aus lockerer Microsoft-Teams-Nachricht einen professionellen IT-Support-Tickettext auf Deutsch.

Antworte ausschließlich als JSON-Objekt mit exakt diesen Feldern:
- "betreff": string
- "inhalt": string

Regeln für den Betreff:
- Kurz, prägnant, sachlich (max. 150 Zeichen)
- Keine Anführungszeichen, kein Punkt am Ende
- Keine Meta-Phrasen wie "Ticket erstellen" oder "Bitte um Hilfe"
- Beschreibe das eigentliche Anliegen oder Problem

Regeln für den Inhalt:
- Vollständige, verständliche Beschreibung des Anliegens in sachlichem Ton
- Entferne Füllwörter und die ursprüngliche Aufforderung, ein Ticket zu erstellen
- Behalte alle relevanten Details (Geräte, Orte, Fehlermeldungen, Dringlichkeit)
- Kein Markdown, nur Fließtext (ggf. mit kurzen Absätzen)
- Schreibe in der dritten Person oder als neutrale Beschreibung ("Der Nutzer meldet …" / "Es wird um … gebeten")
PROMPT;

    /**
     * @return array{betreff: string, inhalt: string}
     */
    public static function parseResponse(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/u', $content, $matches) === 1) {
            $content = trim($matches[1]);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('KI-Antwort ist kein gültiges JSON.');
        }

        $subject = trim((string) ($decoded['betreff'] ?? ''));
        $body = trim((string) ($decoded['inhalt'] ?? ''));

        if ($subject === '' || $body === '') {
            throw new RuntimeException('KI-Antwort enthält keinen vollständigen Betreff oder Inhalt.');
        }

        return [
            'betreff' => Str::limit($subject, 150, ''),
            'inhalt' => $body,
        ];
    }
}
