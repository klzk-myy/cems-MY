<?php

namespace App\Exceptions\Domain;

class SessionOwnershipException extends DomainException
{
    public function __construct(string $message = 'Session does not belong to the specified user')
    {
        parent::__construct($message);
    }
}
