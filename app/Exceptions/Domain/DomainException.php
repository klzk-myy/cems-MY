<?php

namespace App\Exceptions\Domain;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    public function getStatusCode(): int
    {
        return 422;
    }

    public function getSeverity(): string
    {
        return 'warning';
    }

    public function getErrorCode(): string
    {
        return class_basename(static::class);
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
