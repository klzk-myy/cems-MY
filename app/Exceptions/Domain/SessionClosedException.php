<?php

namespace App\Exceptions\Domain;

class SessionClosedException extends DomainException
{
    public function __construct(string $message = 'Session is not open')
    {
        parent::__construct($message);
    }
}
