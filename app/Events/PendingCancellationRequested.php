<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PendingCancellationRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public User $requester,
        public string $reason
    ) {}
}
