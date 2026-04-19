<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class OpenPeriodsException extends RuntimeException
{
    public function __construct(int $openPeriods)
    {
        parent::__construct(
            "Cannot close fiscal year: {$openPeriods} period(s) are still open. Close all periods first."
        );
    }
}
