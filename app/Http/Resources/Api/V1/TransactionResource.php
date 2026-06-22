<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a transaction into a JSON resource.
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
            'amount_local' => $this->amount_local,
            'amount_foreign' => $this->amount_foreign,
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
            'flags' => $this->whenLoaded('flags'),
        ];
    }
}
