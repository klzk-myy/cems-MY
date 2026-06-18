<?php

namespace App\Exceptions\Domain;

class TellerBranchRequiredException extends DomainException
{
    public function __construct(string $message = 'Teller must be assigned to a branch')
    {
        parent::__construct($message);
    }
}
