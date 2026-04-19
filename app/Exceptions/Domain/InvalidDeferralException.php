<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class InvalidDeferralException extends InvalidArgumentException
{
    public function __construct(string $message = 'Only Enhanced CDD transactions support deferred entries')
    {
        parent::__construct($message);
    }
}
