<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\BranchClosureStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a branch closure workflow into a JSON resource.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $initiated_by
 * @property BranchClosureStatus $status
 * @property array<string, mixed>|null $checklist
 * @property Carbon|null $settlement_at
 * @property Carbon|null $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BranchClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'initiated_by' => $this->initiated_by,
            'status' => $this->status,
            'checklist' => $this->checklist,
            'settlement_at' => $this->settlement_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'initiator' => new UserResource($this->whenLoaded('initiator')),
        ];
    }
}
