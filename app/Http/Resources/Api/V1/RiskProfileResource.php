<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a customer risk profile into a JSON resource.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|string|null $risk_score
 * @property string|null $risk_tier
 * @property array<int, mixed>|null $risk_factors
 * @property int|string|null $previous_score
 * @property Carbon|null $score_changed_at
 * @property string|null $recalculation_trigger
 * @property Carbon|null $locked_until
 * @property int|null $locked_by
 * @property string|null $lock_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RiskProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'risk_score' => $this->risk_score,
            'risk_tier' => $this->risk_tier,
            'risk_factors' => $this->risk_factors,
            'previous_score' => $this->previous_score,
            'score_changed_at' => $this->score_changed_at?->toIso8601String(),
            'recalculation_trigger' => $this->recalculation_trigger,
            'locked_until' => $this->locked_until?->toIso8601String(),
            'locked_by' => $this->locked_by,
            'lock_reason' => $this->lock_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
