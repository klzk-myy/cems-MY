<?php

namespace App\Exceptions\Domain;

class TillAlreadyOpenException extends DomainException
{
    public function __construct(public readonly string $counterCode)
    {
        parent::__construct("Counter {$counterCode} is already open today");
    }
}
