<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Public-facing summary of a transaction for nested customer payloads.
 *
 * Whitelists operational fields only - excludes journal/accounting
 * internals, rate-override workflow columns and other bookkeeping data.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $user_id
 * @property int|null $branch_id
 * @property TransactionType $type
 * @property string $currency_code
 * @property string|null $counterparty_country
 * @property string $amount_myr
 * @property string $quantity
 * @property string $rate
 * @property string|null $base_rate
 * @property string|null $purpose
 * @property string|null $source_of_funds
 * @property TransactionStatus $status
 * @property string|null $hold_reason
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'currency_code' => $this->currency_code,
            'counterparty_country' => $this->counterparty_country,
            'amount_myr' => $this->amount_myr,
            'quantity' => $this->quantity,
            'rate' => $this->rate,
            'base_rate' => $this->base_rate,
            'purpose' => $this->purpose,
            'source_of_funds' => $this->source_of_funds,
            'status' => $this->status,
            'hold_reason' => $this->hold_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
