<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class TellerBranchRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Teller must be assigned to a branch')
    {
        parent::__construct($message);
    }
}
