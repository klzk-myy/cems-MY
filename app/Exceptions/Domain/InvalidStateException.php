<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class InvalidStateException extends InvalidArgumentException
{
    public function __construct(string $message = 'Invalid state')
    {
        parent::__construct($message);
    }
}
