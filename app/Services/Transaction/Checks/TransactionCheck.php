<?php

namespace App\Services\Transaction\Checks;

use App\Models\Transaction;

interface TransactionCheck
{
    /**
     * @return array<int, FlagDescriptor>
     */
    public function check(Transaction $transaction): array;
}
