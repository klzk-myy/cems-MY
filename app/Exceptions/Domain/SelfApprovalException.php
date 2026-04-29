<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class SelfApprovalException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('You cannot approve your own transaction. Segregation of duties requires a different approver.');
    }
}
