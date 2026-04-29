<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class UnauthorizedException extends RuntimeException
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message);
    }
}
