<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class PoolAllocationException extends RuntimeException
{
    public function __construct(string $message = 'Failed to allocate from branch pool')
    {
        parent::__construct($message);
    }
}
