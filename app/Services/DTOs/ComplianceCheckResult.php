<?php

namespace App\Services\DTOs;

class ComplianceCheckResult
{
    public function __construct(
        public readonly bool $requiresHold,
        public readonly array $reasons = [],
        public readonly ?string $cddLevel = null,
    ) {}
}
