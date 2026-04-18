<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class TillAlreadyOpenException extends RuntimeException
{
    public function __construct(public readonly string $counterCode)
    {
        parent::__construct("Counter {$counterCode} is already open today");
    }
}
