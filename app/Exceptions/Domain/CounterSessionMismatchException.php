<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class CounterSessionMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $counterCode,
        public readonly string $expectedUser,
        public readonly string $actualUser
    ) {
        parent::__construct(
            "Counter {$counterCode} is already open by user {$actualUser}. Expected: {$expectedUser}"
        );
    }
}
