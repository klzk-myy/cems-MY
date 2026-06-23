<?php

namespace App\Exceptions\Domain;

class InvalidStateException extends DomainException
{
    public function __construct(string $message = 'Invalid state')
    {
        parent::__construct($message);
    }
}
