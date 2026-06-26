<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionApproved
{
    use Dispatchable, SerializesModels;

    public Transaction $transaction;

    public ?int $approverId = null;

    public function __construct(Transaction $transaction, ?int $approverId = null)
    {
        $this->transaction = $transaction;
        $this->approverId = $approverId;
    }
}
