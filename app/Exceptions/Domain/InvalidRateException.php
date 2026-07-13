<?php

namespace App\Exceptions\Domain;

class InvalidRateException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
