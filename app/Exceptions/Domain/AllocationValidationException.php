<?php

namespace App\Exceptions\Domain;

class AllocationValidationException extends DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct("Allocation validation failed: {$reason}");
    }
}
