<?php

namespace App\Exceptions\Domain;

class ClosedPeriodException extends DomainException
{
    public function __construct(string $periodCode)
    {
        parent::__construct(
            "Cannot create entry in closed period {$periodCode}. Please use an open period or contact administrator."
        );
    }
}
