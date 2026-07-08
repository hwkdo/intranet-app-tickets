<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Data\ResolvedTeamsTicketMessage;

class TeamsTicketMessageContentResolver
{
    /**
     * @var list<string>
     */
    private const PLACEHOLDER_CONTENT = [
        'dafür',
        'dafuer',
        'dazu',
        'dies',
        'das',
        'dem',
        'den',
        'denen',
        'dieses',
        'diesem',
        'dieser',
        'hierfür',
        'hierfuer',
        'hierzu',
        'darüber',
        'darueber',
        'damit',
        'daraus',
        'deswegen',
    ];

    public function resolve(
        string $parsedBody,
        ?string $quotedText,
        ?string $quotedSenderName = null,
        bool $isDirectMessage = false,
    ): ResolvedTeamsTicketMessage {
        $body = trim($parsedBody);
        $quoted = trim((string) $quotedText);

        if ($quoted !== '' && ($body === '' || $this->isPlaceholderContent($body))) {
            return new ResolvedTeamsTicketMessage(
                content: $this->formatReferencedContent($quoted, $quotedSenderName, $isDirectMessage),
                contentFromQuote: true,
            );
        }

        return new ResolvedTeamsTicketMessage(
            content: $body,
            contentFromQuote: false,
        );
    }

    private function isPlaceholderContent(string $content): bool
    {
        $normalized = mb_strtolower(trim($content));

        return in_array($normalized, self::PLACEHOLDER_CONTENT, true);
    }

    private function formatReferencedContent(
        string $quotedText,
        ?string $quotedSenderName,
        bool $isDirectMessage,
    ): string {
        if (filled($quotedSenderName)) {
            $label = $isDirectMessage ? 'Weitergeleitete Nachricht von' : 'Zitierte Nachricht von';

            return $label.' '.$quotedSenderName.":\n".$quotedText;
        }

        if ($isDirectMessage) {
            return "Weitergeleitete Nachricht:\n".$quotedText;
        }

        return $quotedText;
    }
}
