<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\FlaggedTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a transaction into a JSON resource.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $user_id
 * @property int|null $branch_id
 * @property int|null $counter_id
 * @property string|null $till_id
 * @property TransactionType $type
 * @property string $currency_code
 * @property string|null $counterparty_country
 * @property string $amount_myr
 * @property string $quantity
 * @property string $rate
 * @property string|null $base_rate
 * @property bool $rate_override
 * @property int|null $rate_override_approved_by
 * @property Carbon|null $rate_override_approved_at
 * @property string|null $purpose
 * @property string|null $source_of_funds
 * @property string|null $source_of_wealth
 * @property TransactionStatus $status
 * @property string|null $hold_reason
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property CddLevel $cdd_level
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property int|null $original_transaction_id
 * @property bool $is_refund
 * @property int|null $journal_entry_id
 * @property int|null $deferred_journal_entry_id
 * @property Carbon|null $journal_entries_created_at
 * @property bool $has_deferred_accounting
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, FlaggedTransaction>|null $flags
 */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'user_id' => $this->user_id,
            'branch_id' => $this->branch_id,
            'counter_id' => $this->counter_id,
            'till_id' => $this->till_id,
            'type' => $this->type,
            'currency_code' => $this->currency_code,
            'counterparty_country' => $this->counterparty_country,
            'amount_myr' => $this->amount_myr,
            'quantity' => $this->quantity,
            'rate' => $this->rate,
            'base_rate' => $this->base_rate,
            'rate_override' => $this->rate_override,
            'rate_override_approved_by' => $this->rate_override_approved_by,
            'rate_override_approved_at' => $this->rate_override_approved_at?->toIso8601String(),
            'purpose' => $this->purpose,
            'source_of_funds' => $this->source_of_funds,
            'source_of_wealth' => $this->source_of_wealth,
            'status' => $this->status,
            'hold_reason' => $this->hold_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'cdd_level' => $this->cdd_level,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->cancelled_by,
            'cancellation_reason' => $this->cancellation_reason,
            'original_transaction_id' => $this->original_transaction_id,
            'is_refund' => $this->is_refund,
            'journal_entry_id' => $this->journal_entry_id,
            'deferred_journal_entry_id' => $this->deferred_journal_entry_id,
            'journal_entries_created_at' => $this->journal_entries_created_at?->toIso8601String(),
            'has_deferred_accounting' => $this->has_deferred_accounting,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'user' => new UserResource($this->whenLoaded('user')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'flags' => $this->whenLoaded(
                'flags',
                fn () => FlagResource::collection($this->flags)
            ),
        ];
    }
}
