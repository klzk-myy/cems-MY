<?php

namespace App\Services\DTOs;

class ValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}
}
