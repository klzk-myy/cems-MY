<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\EddRiskLevel;
use App\Enums\EddStatus;
use App\Enums\EmploymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform an enhanced due diligence record into a JSON resource.
 *
 * @property int $id
 * @property int|null $flagged_transaction_id
 * @property int|null $customer_id
 * @property string|null $edd_reference
 * @property EddRiskLevel|null $risk_level
 * @property EddStatus $status
 * @property string|null $source_of_funds
 * @property string|null $source_of_funds_description
 * @property string|null $purpose_of_transaction
 * @property string|null $business_justification
 * @property EmploymentStatus|null $employment_status
 * @property string|null $employer_name
 * @property string|null $employer_address
 * @property string|null $annual_income_range
 * @property string|null $estimated_net_worth
 * @property string|null $source_of_wealth
 * @property string|null $source_of_wealth_description
 * @property string|null $additional_information
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property array<int, mixed>|null $questionnaire_responses
 * @property Carbon|null $questionnaire_completed_at
 * @property int|null $questionnaire_completed_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EddRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flagged_transaction_id' => $this->flagged_transaction_id,
            'customer_id' => $this->customer_id,
            'edd_reference' => $this->edd_reference,
            'risk_level' => $this->risk_level,
            'status' => $this->status,
            'source_of_funds' => $this->source_of_funds,
            'source_of_funds_description' => $this->source_of_funds_description,
            'purpose_of_transaction' => $this->purpose_of_transaction,
            'business_justification' => $this->business_justification,
            'employment_status' => $this->employment_status,
            'employer_name' => $this->employer_name,
            'employer_address' => $this->employer_address,
            'annual_income_range' => $this->annual_income_range,
            'estimated_net_worth' => $this->estimated_net_worth,
            'source_of_wealth' => $this->source_of_wealth,
            'source_of_wealth_description' => $this->source_of_wealth_description,
            'additional_information' => $this->additional_information,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,
            'questionnaire_responses' => $this->questionnaire_responses,
            'questionnaire_completed_at' => $this->questionnaire_completed_at?->toIso8601String(),
            'questionnaire_completed_by' => $this->questionnaire_completed_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'flagged_transaction' => new FlagResource($this->whenLoaded('flaggedTransaction')),
        ];
    }
}
