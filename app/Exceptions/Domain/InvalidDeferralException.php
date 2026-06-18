<?php

namespace App\Exceptions\Domain;

class InvalidDeferralException extends DomainException
{
    public function __construct(string $message = 'Only Enhanced CDD transactions support deferred entries')
    {
        parent::__construct($message);
    }
}
