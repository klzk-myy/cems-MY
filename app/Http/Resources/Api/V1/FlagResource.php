<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Lean representation of a compliance flag for teller-facing payloads.
 *
 * Deliberately excludes notes, assigned_to, reviewed_by and reviewer
 * attribution - those are restricted to compliance workflows.
 *
 * @property ComplianceFlagType $flag_type
 * @property FlagStatus|null $status
 * @property Carbon|null $created_at
 */
class FlagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'flag_type' => $this->flag_type,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
