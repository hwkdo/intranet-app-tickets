<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

class ZammadArticleBodyRenderer
{
    private const TICKET_ATTACHMENT_PATTERN = '~(?:https?://[^"\']+)?/api/v1/ticket_attachment/(\d+)/(\d+)/(\d+)(?:\?[^"\']*)?~i';

    /**
     * @param  list<array<string, mixed>>  $attachments
     */
    public function render(string $body, int $ticketId, int $articleId, array $attachments = []): string
    {
        if ($body === '') {
            return $body;
        }

        $body = $this->rewriteTicketAttachmentUrls($body, $ticketId, $articleId);
        $body = $this->rewriteCidReferences($body, $ticketId, $articleId, $attachments);

        return $body;
    }

    private function rewriteTicketAttachmentUrls(string $body, int $ticketId, int $articleId): string
    {
        return (string) preg_replace_callback(
            self::TICKET_ATTACHMENT_PATTERN,
            function (array $matches) use ($ticketId, $articleId): string {
                if ((int) $matches[1] !== $ticketId || (int) $matches[2] !== $articleId) {
                    return $matches[0];
                }

                return route('apps.tickets.attachments.download', [
                    'ticketId' => $ticketId,
                    'articleId' => $articleId,
                    'attachmentId' => (int) $matches[3],
                ]);
            },
            $body,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     */
    private function rewriteCidReferences(string $body, int $ticketId, int $articleId, array $attachments): string
    {
        if ($attachments === [] || ! str_contains($body, 'cid:')) {
            return $body;
        }

        return (string) preg_replace_callback(
            '/(<img[[:space:]](?:[^>]*?)src=")cid:([^"]+)"((?:[^>]*?)>)/im',
            function (array $matches) use ($ticketId, $articleId, $attachments): string {
                $cid = $matches[2];

                foreach ($attachments as $attachment) {
                    if (! $this->contentIdMatches($attachment, $cid)) {
                        continue;
                    }

                    $url = route('apps.tickets.attachments.download', [
                        'ticketId' => $ticketId,
                        'articleId' => $articleId,
                        'attachmentId' => (int) ($attachment['id'] ?? 0),
                    ]);

                    return $matches[1].$url.'"'.$matches[3];
                }

                return $matches[0];
            },
            $body,
        );
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function contentIdMatches(array $attachment, string $cid): bool
    {
        $preferences = $attachment['preferences'] ?? [];
        $contentId = $preferences['Content-ID'] ?? $preferences['content_id'] ?? null;

        if ($contentId === null || $contentId === '') {
            return false;
        }

        $normalizedCid = trim($cid, '<>');
        $normalizedContentId = trim((string) $contentId, '<>');

        return $normalizedContentId === $normalizedCid
            || (string) $contentId === $cid
            || (string) $contentId === '<'.$normalizedCid.'>';
    }
}
