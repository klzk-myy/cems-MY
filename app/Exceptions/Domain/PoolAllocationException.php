<?php

namespace App\Exceptions\Domain;

class PoolAllocationException extends DomainException
{
    public function __construct(string $message = 'Failed to allocate from branch pool')
    {
        parent::__construct($message);
    }
}
