<?php

namespace App\Services\DTOs;

class RateCopyResult
{
    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $rates = [],
        public readonly ?string $copiedFromDate = null,
    ) {}
}
