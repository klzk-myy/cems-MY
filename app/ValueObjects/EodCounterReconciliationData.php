<?php

namespace App\ValueObjects;

use App\Models\Counter;
use App\Models\CounterSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EodCounterReconciliationData
{
    /**
     * @param  Collection<int, mixed>  $tillBalances
     * @param  Collection<int, mixed>  $transactions
     * @param  Collection<int, mixed>  $flaggedTransactions
     * @param  Collection<int, mixed>  $handovers
     */
    public function __construct(
        public readonly Counter $counter,
        public readonly Carbon $date,
        public readonly CounterSession $session,
        public readonly Collection $tillBalances,
        public readonly Collection $transactions,
        public readonly Collection $flaggedTransactions,
        public readonly Collection $handovers,
    ) {}
}
