<?php

namespace App\Services\Transaction\DTOs;

use App\Enums\CddLevel;
use App\Models\Customer;
use App\Models\TillBalance;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array{type: string, currency_code: string, amount_foreign: string, rate: string, purpose: string, source_of_funds: string, source_of_wealth?: string, idempotency_key?: string, customer_id: int, till_id: string} $data
 * @property Customer $customer
 * @property TillBalance $tillBalance
 * @property CddLevel $cddLevel
 * @property bool $holdRequired
 * @property Model|null $allocation Teller allocation for update (null for non-tellers)
 */
final class TransactionCreationContext
{
    public function __construct(
        public readonly array $data,
        public readonly Customer $customer,
        public readonly TillBalance $tillBalance,
        public readonly CddLevel $cddLevel,
        public readonly bool $holdRequired,
        public readonly ?Model $allocation = null
    ) {}
}
