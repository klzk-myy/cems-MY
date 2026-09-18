<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\AlertPriority;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a compliance alert into a JSON resource.
 *
 * @property int $id
 * @property int|null $flagged_transaction_id
 * @property int|null $customer_id
 * @property ComplianceFlagType|null $type
 * @property AlertPriority|null $priority
 * @property int|null $risk_score
 * @property string|null $reason
 * @property string|null $source
 * @property int|null $assigned_to
 * @property int|null $case_id
 * @property FlagStatus|null $status
 * @property int|null $reviewed_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $escalated_at
 * @property string|null $escalation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flagged_transaction_id' => $this->flagged_transaction_id,
            'customer_id' => $this->customer_id,
            'type' => $this->type,
            'priority' => $this->priority,
            'risk_score' => $this->risk_score,
            'reason' => $this->reason,
            'source' => $this->source,
            'assigned_to' => $this->assigned_to,
            'case_id' => $this->case_id,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'escalation_reason' => $this->escalation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'flagged_transaction' => new FlagResource($this->whenLoaded('flaggedTransaction')),
            'assigned_to_user' => new UserResource($this->whenLoaded('assignedTo')),
            'case' => new Compliance\CaseResource($this->whenLoaded('case')),
        ];
    }
}
