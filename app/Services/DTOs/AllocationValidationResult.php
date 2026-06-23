<?php

namespace App\Services\DTOs;

class AllocationValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $reason = null,
        public readonly ?object $allocation = null,
    ) {}
}
