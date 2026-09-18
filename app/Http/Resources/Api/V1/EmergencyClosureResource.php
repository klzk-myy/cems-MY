<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform an emergency counter closure into a JSON resource.
 *
 * @property int $id
 * @property int $counter_id
 * @property int $session_id
 * @property int $teller_id
 * @property string $reason
 * @property Carbon|null $closed_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmergencyClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'counter_id' => $this->counter_id,
            'session_id' => $this->session_id,
            'teller_id' => $this->teller_id,
            'reason' => $this->reason,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'counter' => new CounterResource($this->whenLoaded('counter')),
            'session' => new CounterSessionResource($this->whenLoaded('session')),
        ];
    }
}
