<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Data;

readonly class ZammadBulkActionResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $succeeded = 0,
        public int $failed = 0,
        public int $skipped = 0,
        public array $errors = [],
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }
}
