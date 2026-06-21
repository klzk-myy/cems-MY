<?php

namespace App\Models\Compliance;

use App\Enums\EddDocumentStatus;
use App\Models\BaseModel;
use App\Models\EnhancedDiligenceRecord;
use App\Models\Traits\HasStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EddDocumentRequest extends BaseModel
{
    use HasFactory;
    use HasStatus;

    protected $with = ['eddRecord', 'verifier'];

    protected $fillable = [
        'edd_record_id',
        'document_type',
        'file_path',
        'status',
        'rejection_reason',
        'uploaded_at',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'status' => EddDocumentStatus::class,
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the statuses considered active for this model.
     *
     * @return array<int, EddDocumentStatus>
     */
    protected function activeStatusValues(): array
    {
        return [
            EddDocumentStatus::Pending,
            EddDocumentStatus::Received,
        ];
    }

    /**
     * Get the statuses considered open for this model.
     *
     * @return array<int, EddDocumentStatus>
     */
    protected function openStatusValues(): array
    {
        return [
            EddDocumentStatus::Pending,
            EddDocumentStatus::Received,
        ];
    }

    public function eddRecord(): BelongsTo
    {
        return $this->belongsTo(EnhancedDiligenceRecord::class, 'edd_record_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function markReceived(string $filePath): void
    {
        $this->update([
            'status' => EddDocumentStatus::Received->value,
            'file_path' => $filePath,
            'uploaded_at' => now(),
        ]);
    }

    public function verify(int $verifiedBy): void
    {
        $this->update([
            'status' => EddDocumentStatus::Verified->value,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);
    }

    public function reject(string $reason, int $verifiedBy): void
    {
        $this->update([
            'status' => EddDocumentStatus::Rejected->value,
            'rejection_reason' => $reason,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);
    }
}
