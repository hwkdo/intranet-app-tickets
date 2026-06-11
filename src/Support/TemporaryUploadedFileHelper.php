<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Support;

use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class TemporaryUploadedFileHelper
{
    public static function sizeBytes(UploadedFile $file): int
    {
        if ($file instanceof TemporaryUploadedFile) {
            $meta = $file->metaFileData();
            $metaSize = isset($meta['size']) ? (int) $meta['size'] : 0;

            if ($metaSize > 0) {
                return $metaSize;
            }

            if (! $file->exists()) {
                throw new RuntimeException(sprintf(
                    'Der Anhang «%s» ist nicht verfügbar. Bitte warten Sie, bis der Upload abgeschlossen ist, oder wählen Sie die Datei erneut aus.',
                    $file->getClientOriginalName(),
                ));
            }
        }

        return $file->getSize();
    }

    public static function assertAvailable(UploadedFile $file): void
    {
        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        if ($file->exists()) {
            return;
        }

        $meta = $file->metaFileData();
        if (isset($meta['size']) && (int) $meta['size'] > 0) {
            throw new RuntimeException(sprintf(
                'Der Anhang «%s» ist nicht verfügbar. Bitte wählen Sie die Datei erneut aus.',
                $file->getClientOriginalName(),
            ));
        }

        throw new RuntimeException(sprintf(
            'Der Anhang «%s» ist nicht verfügbar. Bitte warten Sie, bis der Upload abgeschlossen ist, oder wählen Sie die Datei erneut aus.',
            $file->getClientOriginalName(),
        ));
    }

    /**
     * @return resource
     */
    public static function readStream(UploadedFile $file)
    {
        if ($file instanceof TemporaryUploadedFile) {
            self::assertAvailable($file);

            $stream = $file->readStream();

            if ($stream !== false) {
                return $stream;
            }
        }

        $realPath = $file->getRealPath();

        if ($realPath === false) {
            throw new RuntimeException('Die Upload-Datei konnte nicht gelesen werden.');
        }

        $stream = fopen($realPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Die Upload-Datei konnte nicht geöffnet werden.');
        }

        return $stream;
    }
}
