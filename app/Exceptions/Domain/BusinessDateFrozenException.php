<?php

namespace App\Exceptions\Domain;

class BusinessDateFrozenException extends DomainException
{
    public function __construct(string $branchCode, string $date)
    {
        parent::__construct(
            "Cannot post to {$branchCode} for {$date}: the business date is closed. A privileged user may reopen the day from the branch closing page."
        );
    }
}
