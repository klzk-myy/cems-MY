<?php

namespace App\Exceptions\Domain;

class AccountNotFoundException extends DomainException
{
    public function __construct(string $accountCode)
    {
        parent::__construct("Account not found: {$accountCode}");
    }
}
