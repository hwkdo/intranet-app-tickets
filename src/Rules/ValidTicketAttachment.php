<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Rules;

use Closure;
use Hwkdo\IntranetAppTickets\Support\TemporaryUploadedFileHelper;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidTicketAttachment implements ValidationRule
{
    public function __construct(private readonly int $maxKilobytes = 20480) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! $value instanceof UploadedFile) {
            $fail('Die Datei :attribute ist ungültig.');

            return;
        }

        try {
            TemporaryUploadedFileHelper::assertAvailable($value);
            $sizeBytes = TemporaryUploadedFileHelper::sizeBytes($value);
        } catch (\RuntimeException $exception) {
            $fail($exception->getMessage());

            return;
        }

        if ($sizeBytes > ($this->maxKilobytes * 1024)) {
            $fail('Der Anhang darf maximal '.$this->maxKilobytes.' MB groß sein.');
        }
    }
}
