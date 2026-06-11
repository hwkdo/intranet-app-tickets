<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Models\TicketRequestAttachment;
use Hwkdo\IntranetAppTickets\Support\TemporaryUploadedFileHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TicketAttachmentStorage
{
    private const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    /**
     * @param  list<UploadedFile>  $files
     */
    public function storeForRequest(TicketRequest $ticketRequest, array $files): void
    {
        $files = array_values(array_filter($files));

        if ($files === []) {
            return;
        }

        foreach ($files as $file) {
            TemporaryUploadedFileHelper::assertAvailable($file);
        }

        $totalSize = collect($files)->sum(
            fn (UploadedFile $file): int => TemporaryUploadedFileHelper::sizeBytes($file),
        );

        if ($totalSize > self::MAX_TOTAL_BYTES) {
            throw new RuntimeException('Die Anhänge sind zusammen größer als 20 MB.');
        }

        foreach ($files as $index => $file) {
            $targetPath = 'ticket-requests/'.$ticketRequest->id.'/'.($index + 1).'_'.$file->getClientOriginalName();

            $stream = TemporaryUploadedFileHelper::readStream($file);

            try {
                $stored = Storage::disk('local')->put($targetPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $stored || ! Storage::disk('local')->exists($targetPath)) {
                throw new RuntimeException('Der Anhang konnte nicht gespeichert werden.');
            }

            $size = TemporaryUploadedFileHelper::sizeBytes($file);

            if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                $file->delete();
            }

            TicketRequestAttachment::query()->create([
                'ticket_request_id' => $ticketRequest->id,
                'disk' => 'local',
                'path' => $targetPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $size,
            ]);
        }
    }
}
