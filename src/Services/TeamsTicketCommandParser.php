<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Data\ParsedTeamsTicketCommand;
use Illuminate\Support\Str;

class TeamsTicketCommandParser
{
    /**
     * Trigger-Muster für „Ticket erstellen" in natürlicher Sprache. Der Inhalt wird in der
     * benannten Gruppe `content` erfasst.
     *
     * @var list<string>
     */
    private const PATTERNS = [
        // "... ticket erstellen/anlegen: <content>" (Verb nach dem Wort Ticket)
        '/\bticket(?:s)?\s+(?:zu\s+)?(?:erstellen|anlegen|erfassen|aufmachen|öffnen|oeffnen)\b[\s,:\-–]*(?<content>.*)$/isu',
        // "erstelle/leg/mach ... ticket [dass|über|für|:] <content>"
        '/\b(?:erstelle?|erstell|lege?|mach(?:e)?|öffne|oeffne|erfasse?)\b[^:]*?\bticket(?:s)?\b[\s,]*(?:dass|das|welches|worin|über|ueber|für|fuer|zu|mit(?:\s+dem\s+inhalt)?|wonach|betreff)?[\s,:\-–]*(?<content>.*)$/isu',
        // "ticket: <content>" / "neues ticket - <content>"
        '/^\s*(?:neues\s+)?ticket(?:s)?\b[\s,]*[:\-–][\s,]*(?<content>.*)$/isu',
    ];

    public function parse(string $text): ?ParsedTeamsTicketCommand
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($normalized === '') {
            return null;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized, $matches) !== 1) {
                continue;
            }

            $content = $this->cleanContent($matches['content'] ?? '');

            return new ParsedTeamsTicketCommand(
                subject: $this->buildSubject($content),
                body: $content,
            );
        }

        return null;
    }

    private function cleanContent(string $content): string
    {
        $content = trim($content);

        $content = (string) preg_replace(
            '/^(?:dass|das|welches|worin|über|ueber|für|fuer|zu|mit dem inhalt|mit|wonach|betreff)\b[\s,:\-–]*/iu',
            '',
            $content,
        );

        return trim($content, " \t\n\r\0\x0B,:-–");
    }

    private function buildSubject(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $firstLine = trim((string) Str::of($content)->before("\n"));

        $subject = trim((string) Str::limit($firstLine, 120, ''));

        return $subject !== '' ? $subject : trim((string) Str::limit($content, 120, ''));
    }
}
