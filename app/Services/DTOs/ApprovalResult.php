<?php

namespace App\Services\DTOs;

use App\Models\Transaction;

class ApprovalResult
{
    /**
     * @param  bool  $success  Whether approval succeeded
     * @param  string  $message  User-facing message
     * @param  Transaction|null  $transaction  The approved transaction (if success)
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?Transaction $transaction = null
    ) {}
}
