# Transaction Services Architecture

This document describes the decomposition of the monolithic `TransactionService` into focused domain services.

## Extracted Services (Phase 2)

### TransactionHoldService
- Determines if a transaction requires a hold based on CDD level and risk flags.
- Hold reasons: Enhanced CDD, critical risk flags.
- Interface: `App\Services\Contracts\TransactionHoldServiceInterface`
- Implementation: `App\Services\Transaction\TransactionHoldService`

### TransactionIdempotencyService
- Handles duplicate detection: idempotency key lookup and recent duplicate detection (30s window).
- Interface: `App\Services\Contracts\TransactionIdempotencyServiceInterface`
- Implementation: `App\Services\Transaction\TransactionIdempotencyService`

## Dependency Flow

```
TransactionService
   ├── TransactionHoldService (via interface)
   └── TransactionIdempotencyService (via interface)
```

## Usage Examples

### In TransactionService

```php
// Hold determination
$holdRequired = $this->holdService->requiresHold($cddLevel, $customer, $riskFlags);

// Idempotency check
$existing = $this->idempotencyService->findDuplicate($idempotencyKey, $userId, $data);
$recentDuplicate = $this->idempotencyService->checkRecentDuplicate($userId, $data, 30);
```

## Next Phases

- Phase 3: Extract TransactionStatusService and TransactionValidationService
- Phase 4: Extract TransactionCreationService
- Phase 5: Extract TransactionApprovalService
- Phase 6: Update controllers to use new services directly
