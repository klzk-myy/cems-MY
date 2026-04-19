<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class AccountNotFoundException extends InvalidArgumentException
{
    public function __construct(string $accountCode)
    {
        parent::__construct("Account not found: {$accountCode}");
    }
}
