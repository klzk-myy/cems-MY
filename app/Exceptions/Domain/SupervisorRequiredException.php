<?php

namespace App\Exceptions\Domain;

class SupervisorRequiredException extends DomainException
{
    public function __construct(string $message = 'Supervisor must be a manager or admin')
    {
        parent::__construct($message);
    }
}
