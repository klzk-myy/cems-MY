<?php

namespace App\Exceptions\Domain;

class UserAlreadyAtCounterException extends DomainException
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct("User {$userId} is already assigned to another counter");
    }
}
