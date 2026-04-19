<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class InvalidAllocationStateException extends RuntimeException
{
    public function __construct(string $requiredState = 'approved')
    {
        parent::__construct("Can only activate {$requiredState} allocation");
    }
}
