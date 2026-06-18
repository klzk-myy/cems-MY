<?php

namespace App\Exceptions\Domain;

class SelfApprovalException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You cannot approve your own transaction. Segregation of duties requires a different approver.');
    }
}
