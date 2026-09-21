<?php

namespace App\Services\DTOs;

class RateFetchResult
{
    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $rates = [],
    ) {}
}
