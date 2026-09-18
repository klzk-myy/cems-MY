<?php

namespace App\Exceptions\Domain;

class InvalidAllocationStateException extends DomainException
{
    public function __construct(string $requiredState = 'approved')
    {
        parent::__construct("Can only activate {$requiredState} allocation");
    }

    public function getStatusCode(): int
    {
        return 409;
    }
}
