<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class SessionClosedException extends RuntimeException
{
    public function __construct(string $message = 'Session is not open')
    {
        parent::__construct($message);
    }
}
