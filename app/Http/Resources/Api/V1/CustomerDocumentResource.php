<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Lean representation of a KYC document for nested customer payloads.
 *
 * Deliberately excludes file_path and file_hash so filesystem locations
 * and integrity digests never leave the compliance boundary.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $document_type
 * @property string $status
 * @property int|null $file_size
 * @property Carbon|null $verified_at
 * @property Carbon|null $expiry_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'document_type' => $this->document_type,
            'status' => $this->status,
            'file_size' => $this->file_size,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
