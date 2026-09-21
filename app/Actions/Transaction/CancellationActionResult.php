<?php

namespace App\Actions\Transaction;

use App\Models\Transaction;

class CancellationActionResult
{
    private function __construct(
        public bool $success,
        public string $message,
        public ?Transaction $transaction = null,
        public array $context = []
    ) {}

    public static function success(string $message, ?Transaction $transaction = null, array $context = []): self
    {
        return new self(true, $message, $transaction, $context);
    }

    public static function error(string $message): self
    {
        return new self(false, $message);
    }
}
