<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class SupervisorRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Supervisor must be a manager or admin')
    {
        parent::__construct($message);
    }
}
