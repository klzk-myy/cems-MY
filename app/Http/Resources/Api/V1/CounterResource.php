<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CounterStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a counter into a JSON resource.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property CounterStatus $status
 * @property int $branch_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CounterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
