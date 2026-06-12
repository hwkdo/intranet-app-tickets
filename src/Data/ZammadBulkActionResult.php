<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

readonly class ZammadBulkActionResult
{
    /**
     * @param  list<string>  $errors
     * @param  array<string, int>  $skippedReasons
     */
    public function __construct(
        public int $succeeded = 0,
        public int $failed = 0,
        public int $skipped = 0,
        public array $errors = [],
        public int $processed = 0,
        public array $skippedReasons = [],
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    public function skipSummary(): string
    {
        if ($this->skippedReasons === []) {
            return '';
        }

        return collect($this->skippedReasons)
            ->map(fn (int $count, string $reason): string => $reason.': '.$count)
            ->implode(', ');
    }
}
