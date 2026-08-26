<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Lean representation of a risk score snapshot for nested customer payloads.
 *
 * @property int $id
 * @property Carbon $snapshot_date
 * @property int $overall_score
 * @property int $velocity_score
 * @property int $structuring_score
 * @property int $geographic_score
 * @property int $amount_score
 * @property string $trend
 * @property array|null $factors
 * @property Carbon|null $next_screening_date
 * @property Carbon|null $created_at
 */
class CustomerRiskSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'snapshot_date' => $this->snapshot_date,
            'overall_score' => $this->overall_score,
            'velocity_score' => $this->velocity_score,
            'structuring_score' => $this->structuring_score,
            'geographic_score' => $this->geographic_score,
            'amount_score' => $this->amount_score,
            'trend' => $this->trend,
            'factors' => $this->factors,
            'next_screening_date' => $this->next_screening_date,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
