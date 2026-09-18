<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\TellerAllocationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a teller allocation into a JSON resource.
 *
 * @property int $id
 * @property int $user_id
 * @property int $branch_id
 * @property int|null $counter_id
 * @property string $currency_code
 * @property string|null $allocated_amount
 * @property string|null $current_balance
 * @property string|null $loaded_balance
 * @property string|null $requested_amount
 * @property string|null $daily_limit_myr
 * @property string|null $daily_used_myr
 * @property TellerAllocationStatus $status
 * @property Carbon|null $session_date
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TellerAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'branch_id' => $this->branch_id,
            'counter_id' => $this->counter_id,
            'currency_code' => $this->currency_code,
            'allocated_amount' => $this->allocated_amount,
            'current_balance' => $this->current_balance,
            'loaded_balance' => $this->loaded_balance,
            'requested_amount' => $this->requested_amount,
            'daily_limit_myr' => $this->daily_limit_myr,
            'daily_used_myr' => $this->daily_used_myr,
            'status' => $this->status,
            'session_date' => $this->session_date?->toDateString(),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'counter' => new CounterResource($this->whenLoaded('counter')),
            'approver' => new UserResource($this->whenLoaded('approver')),
        ];
    }
}
