<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class UserAlreadyAtCounterException extends RuntimeException
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct("User {$userId} is already assigned to another counter");
    }
}
