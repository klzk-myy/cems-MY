<?php

namespace App\Services\Transaction\DTOs;

use App\Enums\TransactionStatus;

/**
 * Outcome of InitialStatusResolver: the status a newly created transaction
 * starts in plus the human-readable hold reasons that produced it.
 */
final class InitialStatusResult
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(
        public readonly TransactionStatus $status,
        public readonly ?string $holdReason,
        public readonly array $reasons = [],
    ) {}

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::PendingApproval;
    }
}
