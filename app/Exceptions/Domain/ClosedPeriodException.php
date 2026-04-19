<?php

namespace App\Exceptions\Domain;

use InvalidArgumentException;

class ClosedPeriodException extends InvalidArgumentException
{
    public function __construct(string $periodCode)
    {
        parent::__construct(
            "Cannot create entry in closed period {$periodCode}. Please use an open period or contact administrator."
        );
    }
}
