<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a customer into a JSON resource.
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'id_type' => $this->id_type,
            'id_number_masked' => $this->id_number_masked,
            'nationality' => $this->nationality,
            'date_of_birth' => $this->date_of_birth?->toIso8601String(),
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'pep_status' => $this->pep_status,
            'pep_role_ended_at' => $this->pep_role_ended_at?->toIso8601String(),
            'current_role_domain' => $this->current_role_domain,
            'former_pep_domain' => $this->former_pep_domain,
            'is_pep_associate' => $this->is_pep_associate,
            'sanction_hit' => $this->sanction_hit,
            'risk_score' => $this->risk_score,
            'risk_rating' => $this->risk_rating,
            'cdd_level' => $this->cdd_level,
            'is_active' => $this->is_active,
            'occupation' => $this->occupation,
            'employer_name' => $this->employer_name,
            'employer_address' => $this->employer_address,
            'annual_volume_estimate' => $this->annual_volume_estimate,
            'risk_assessed_at' => $this->risk_assessed_at?->toIso8601String(),
            'last_transaction_at' => $this->last_transaction_at?->toIso8601String(),
            'is_frozen' => $this->is_frozen,
            'freeze_reason' => $this->freeze_reason,
            'frozen_at' => $this->frozen_at?->toIso8601String(),
            'transactions_blocked' => $this->transactions_blocked,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'documents' => $this->whenLoaded('documents'),
            'transactions' => $this->whenLoaded('transactions'),
            'latest_risk_snapshot' => $this->whenLoaded('latestRiskSnapshot'),
        ];
    }
}
