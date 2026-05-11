<?php

namespace App\Exceptions\Domain;

class PepApprovalRequiredException extends \RuntimeException
{
    public function __construct(string $message = 'Senior Management approval required for PEP customer')
    {
        parent::__construct($message);
    }
}
