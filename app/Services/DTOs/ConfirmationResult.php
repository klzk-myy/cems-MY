<?php

namespace App\Services\DTOs;

class ConfirmationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
    ) {}
}
