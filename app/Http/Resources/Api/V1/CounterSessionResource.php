<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CounterSessionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a counter session into a JSON resource.
 *
 * @property int $id
 * @property int $counter_id
 * @property int $user_id
 * @property Carbon|null $session_date
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property int|null $opened_by
 * @property int|null $closed_by
 * @property CounterSessionStatus $status
 * @property string|null $notes
 * @property bool $physical_count_verified
 * @property string|null $handover_notes
 * @property string|null $daily_limit_myr
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CounterSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'counter_id' => $this->counter_id,
            'user_id' => $this->user_id,
            'session_date' => $this->session_date?->toDateString(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'opened_by' => $this->opened_by,
            'closed_by' => $this->closed_by,
            'status' => $this->status,
            'notes' => $this->notes,
            'physical_count_verified' => $this->physical_count_verified,
            'handover_notes' => $this->handover_notes,
            'daily_limit_myr' => $this->daily_limit_myr,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'counter' => new CounterResource($this->whenLoaded('counter')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
