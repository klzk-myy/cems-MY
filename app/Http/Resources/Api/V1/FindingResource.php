<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a compliance finding into a JSON resource.
 *
 * @property int $id
 * @property string|null $finding_type
 * @property FindingSeverity|null $severity
 * @property FindingStatus $status
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $details
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'finding_type' => $this->finding_type,
            'severity' => $this->severity,
            'status' => $this->status,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'details' => $this->details,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
