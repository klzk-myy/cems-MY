<?php

namespace App\Services\DTOs;

use App\Models\TellerAllocation;

class AllocationValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $reason = null,
        public readonly ?TellerAllocation $allocation = null,
    ) {}
}
