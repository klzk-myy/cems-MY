<?php

namespace App\Exceptions\Domain;

class OpenPeriodsException extends DomainException
{
    public function __construct(int $openPeriods)
    {
        parent::__construct(
            "Cannot close fiscal year: {$openPeriods} period(s) are still open. Close all periods first."
        );
    }
}
