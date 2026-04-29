<?php

namespace App\Exceptions\Domain;

class EmergencyCloseSessionTooNewException extends RuntimeException
{
    public function __construct(string $message = 'Emergency close not allowed: session must be at least 30 minutes old')
    {
        parent::__construct($message);
    }
}
