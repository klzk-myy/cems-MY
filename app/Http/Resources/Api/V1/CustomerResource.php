<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CddLevel;
use App\Enums\IdType;
use App\Enums\RiskRating;
use App\Models\CustomerDocument;
use App\Models\RiskScoreSnapshot;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a customer into a JSON resource.
 *
 * @property int $id
 * @property string $full_name
 * @property IdType $id_type
 * @property-read string|null $id_number
 * @property string $nationality
 * @property Carbon $date_of_birth
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property bool $pep_status
 * @property Carbon|null $pep_role_ended_at
 * @property string|null $current_role_domain
 * @property string|null $former_pep_domain
 * @property bool $is_pep_associate
 * @property bool $sanction_hit
 * @property int $risk_score
 * @property RiskRating $risk_rating
 * @property CddLevel $cdd_level
 * @property bool $is_active
 * @property string|null $occupation
 * @property string|null $employer_name
 * @property string|null $employer_address
 * @property float|null $annual_volume_myr
 * @property Carbon|null $risk_assessed_at
 * @property Carbon|null $last_transaction_at
 * @property bool $is_frozen
 * @property string|null $freeze_reason
 * @property Carbon|null $frozen_at
 * @property bool $transactions_blocked
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CustomerDocument>|null $documents
 * @property-read Collection<int, Transaction>|null $transactions
 * @property-read RiskScoreSnapshot|null $latestRiskSnapshot
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'id_type' => $this->id_type,
            'id_number' => $this->id_number,
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
            'annual_volume_myr' => $this->annual_volume_myr,
            'risk_assessed_at' => $this->risk_assessed_at?->toIso8601String(),
            'last_transaction_at' => $this->last_transaction_at?->toIso8601String(),
            'is_frozen' => $this->is_frozen,
            'freeze_reason' => $this->freeze_reason,
            'frozen_at' => $this->frozen_at?->toIso8601String(),
            'transactions_blocked' => $this->transactions_blocked,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'documents' => $this->whenLoaded(
                'documents',
                fn () => CustomerDocumentResource::collection($this->documents)
            ),
            'transactions' => $this->whenLoaded(
                'transactions',
                fn () => CustomerTransactionResource::collection($this->transactions)
            ),
            'latest_risk_snapshot' => $this->whenLoaded(
                'latestRiskSnapshot',
                fn () => new CustomerRiskSnapshotResource($this->latestRiskSnapshot)
            ),
        ];
    }
}
