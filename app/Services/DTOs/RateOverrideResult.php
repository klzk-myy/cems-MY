<?php

namespace App\Services\DTOs;

class RateOverrideResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $previousRate = null,
        public readonly ?string $newRate = null,
    ) {}
}
