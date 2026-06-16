<?php

namespace App\Http\Resources\Api\V1\Compliance;

use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a compliance case into a JSON resource.
 */
class CaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'case_type' => $this->case_type,
            'severity' => $this->severity,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'case_summary' => $this->case_summary,
            'sla_deadline' => $this->sla_deadline?->toIso8601String(),
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution' => $this->resolution,
            'resolution_notes' => $this->resolution_notes,
            'primary_flag_id' => $this->primary_flag_id,
            'primary_finding_id' => $this->primary_finding_id,
            'metadata' => $this->metadata,
            'created_via' => $this->created_via,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
        ];
    }
}
