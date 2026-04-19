<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class SessionOwnershipException extends RuntimeException
{
    public function __construct(string $message = 'Session does not belong to the specified user')
    {
        parent::__construct($message);
    }
}
