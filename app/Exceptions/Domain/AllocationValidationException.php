<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class AllocationValidationException extends InvalidArgumentException
{
    public function __construct(string $reason)
    {
        parent::__construct("Allocation validation failed: {$reason}");
    }
}
