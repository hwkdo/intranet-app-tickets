<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\MsGraphLaravel\Services\TeamsChatMessageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class TeamsTicketQuotedAttachmentResolver
{
    public function __construct(
        private readonly ?TeamsChatMessageService $mediaService = null,
    ) {}

    /**
     * @return list<UploadedFile>
     */
    public function resolve(?string $conversationId, ?string $quotedMessageId): array
    {
        if (! filled($conversationId) || ! filled($quotedMessageId)) {
            return [];
        }

        if (! class_exists(TeamsChatMessageService::class)) {
            return [];
        }

        try {
            $service = $this->mediaService ?? app(TeamsChatMessageService::class);
            $mediaFiles = $service->fetchHostedContents($conversationId, $quotedMessageId);
        } catch (Throwable $exception) {
            Log::warning('Teams-Bot: Anhänge aus zitierter Nachricht konnten nicht geladen werden', [
                'conversation_id' => $conversationId,
                'quoted_message_id' => $quotedMessageId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        return array_map(
            fn (array $file): UploadedFile => $this->toUploadedFile($file),
            $mediaFiles,
        );
    }

    /**
     * @param  array{filename: string, mimeType: string, contents: string}  $file
     */
    private function toUploadedFile(array $file): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'teams_ticket_');

        if ($path === false) {
            throw new \RuntimeException('Temporäre Datei für Teams-Anhang konnte nicht erstellt werden.');
        }

        file_put_contents($path, $file['contents']);

        return new UploadedFile(
            $path,
            $file['filename'],
            $file['mimeType'],
            null,
            true,
        );
    }
}
