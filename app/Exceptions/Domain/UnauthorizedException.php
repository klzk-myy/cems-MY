<?php

namespace App\Exceptions\Domain;

class UnauthorizedException extends DomainException
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 401;
    }
}
