<?php

namespace App\Exceptions\Domain;

class SegregationOfDutiesException extends DomainException
{
    public function __construct(string $action = 'perform this action')
    {
        parent::__construct("Segregation of duties violation: You cannot {$action}. A different person must implement and enforce controls.");
    }
}
