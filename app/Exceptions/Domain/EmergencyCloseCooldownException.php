<?php

namespace App\Exceptions\Domain;

class EmergencyCloseCooldownException extends \RuntimeException
{
    public function __construct(string $message = 'Emergency close not allowed: 4-hour cooldown period active')
    {
        parent::__construct($message);
    }
}
