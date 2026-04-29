<?php

namespace App\Exceptions\Domain;

class InvalidRateException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $message,
    ) {
        parent::__construct($message);
    }
}
