<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\TransactionConfirmationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Transform a transaction confirmation into a JSON resource.
 *
 * The confirmation_token is intentionally omitted — it is the credential
 * that authorizes confirmation and must not be exposed in responses.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $user_id
 * @property TransactionConfirmationStatus $status
 * @property Carbon|null $expires_at
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TransactionConfirmationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'confirmed_by' => $this->confirmed_by,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
