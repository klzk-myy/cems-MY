<?php

namespace App\Actions\Transaction;

use App\Models\Transaction;

class TransactionApprovalResult
{
    private function __construct(
        public bool $success,
        public string $message,
        public ?Transaction $transaction = null
    ) {}

    public static function success(string $message, ?Transaction $transaction = null): self
    {
        return new self(true, $message, $transaction);
    }

    public static function error(string $message): self
    {
        return new self(false, $message);
    }
}
