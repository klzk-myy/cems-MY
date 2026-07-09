<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\System\CacheTagsService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\DTOs\TransactionCreationContext;

class TransactionCreationService implements TransactionCreationServiceInterface
{
    public function __construct(
        protected TransactionIdempotencyServiceInterface $idempotencyService,
        protected CurrencyPositionService $positionService,
        protected TransactionAccountingService $transactionAccountingService,
        protected AuditService $auditService,
        protected TellerAllocationService $tellerAllocationService,
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected CacheTagsService $cacheTagsService
    ) {}

    public function create(TransactionCreationContext $context, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        throw new \BadMethodCallException('Not implemented yet');
    }
}
