# Architecture Improvement Implementation Plan

**Date**: 2025-07-09  
**Repository**: `cems-my`  
**Scope**: Comprehensive technical debt reduction and architectural improvements  
**Priority**: P0-P3 (Critical to Low)  
**Estimated Effort**: 2-3 sprints (4-6 weeks)

---

## Executive Summary

This plan addresses architectural concerns identified during a comprehensive code review of the CEMS My financial compliance application. The primary goals are:

1. ✅ **Reduce complexity** in the monolithic `TransactionService` (906 lines)
2. ✅ **Fix schema consistency** issues (9 violations)
3. ✅ **Clean up orphaned code** (25 candidate views, dead routes)
4. ✅ **Improve maintainability** while preserving regulatory compliance
5. ✅ **Enforce code quality** standards across the codebase

---

## Table of Contents

1. [Findings Summary](#findings-summary)
2. [Risk Assessment](#risk-assessment)
3. [Implementation Phases](#implementation-phases)
4. [Detailed Tasks](#detailed-tasks)
5. [Testing Strategy](#testing-strategy)
6. [Rollback Plan](#rollback-plan)
7. [Success Metrics](#success-metrics)

---

## Findings Summary

### CRITICAL Issues

| Issue | Impact | Risk | Lines Affected |
|-------|--------|------|----------------|
| TransactionService monolith | 16 direct imports, 351 routes depend on it | **HIGH** | 906 |
| Schema fillable/cast mismatches | Potential mass assignment vulns | **HIGH** | 9 models |
| Orphaned views (25 candidates) | Unused code bloat | **MEDIUM** | 25 files |
| Inconsistent route naming | Maintainability | **LOW** | 351 routes |

### MEDIUM Issues

- Inconsistent exception handling (generic `\Exception` catches)
- Hardcoded cache keys
- Mixed naming patterns across route categories
- Inline middleware definitions (349 routes)

### LOW Issues

- Deprecated/commented code (`XXX` markers)
- Missing API documentation
- Some long controller methods (150+ lines)

---

## Risk Assessment

### High-Risk Components

**TransactionService Refactoring**:
- **Blast Radius**: Affects transaction processing, accounting, compliance
- **Downtime Risk**: Low (if done with feature flags and backward compatibility)
- **Data Risk**: Low (no schema changes, only code reorganization)
- **Regulatory Risk**: Medium (must maintain audit trail and compliance logic)

**Schema Fixes**:
- **Blast Radius**: Direct model changes affect all CRUD operations
- **Downtime Risk**: Low (doctrine cache clear needed)
- **Data Risk**: Very Low (only adding fillable/cast, not destructive)

**Orphaned Code Removal**:
- **Blast Radius**: Low (delete unused files)
- **Downtime Risk**: None
- **Data Risk**: None

---

## Implementation Phases

### Phase 0: Preparation (Week 0)

**Objective**: Establish baseline, create feature branches, update tools

Tasks:
1. ✅ Update GitNexus index: `npx gitnexus analyze` (all repos)
2. ✅ Ensure all tests passing: `php artisan test --compact` (green baseline)
3. ✅ Run Pint: `vendor/bin/pint --format agent` (fix style issues)
4. Create feature branch: `git checkout -b feat/architectural-improvements-2025`
5. Backup database: `php artisan backup:run --type=database`
6. Enable debug logging temporarily (if not already)

**Acceptance Criteria**:
- All tests green
- Code styled with Pint
- GitNexus index current
- Backup complete

---

### Phase 1: Schema Consistency Fixes (Week 1)

**Objective**: Resolve 9 fillable/cast mismatches to prevent security issues

**Migration Steps**:

#### Task 1.1: Fix Customer model
**File**: `app/Models/Customer.php`

```php
// ADD to $fillable:
'freeze_reason',
'rejection_reason',

// ADD to $casts:
'freeze_reason' => 'string',
'rejection_reason' => 'string',
```

**Rationale**: These columns exist in `customers` table but are not fillable/cast, causing silent failures when set.

#### Task 1.2: Fix User model
**File**: `app/Models/User.php`

```php
// REMOVE from $fillable:
'password',  // WRONG - column doesn't exist

// ADD to $casts:
'password_hash' => 'string',
```

**Rationale**: `password` field doesn't exist; should be `password_hash`. This could cause mass assignment errors.

#### Task 1.3: Fix Transaction model
**File**: `app/Models/Transaction.php`

```php
// ADD to $fillable (if they should be mass assignable during specific operations):
'version',
'hold_reason',
'cancelled_by',
'cancellation_reason',

// OR add to $casts to prevent mass assignment:
'version' => 'int',
'hold_reason' => 'string',
'cancelled_by' => 'int',
'cancellation_reason' => 'string',
```

**Recommendation**: Add to `$casts` only. These fields are set internally, not via user input.

#### Task 1.4: Fix CurrencyPosition model
**File**: `app/Models/CurrencyPosition.php`

```php
// Verify: Does 'till_id' column exist in currency_positions table?
// If not, REMOVE from $fillable
// If yes, ensure foreign key constraint exists
```

**Validation**: Check database schema before change.

**Testing**: Run `php artisan test --compact --filter=ModelHierarchyTest` and `php artisan test --compact --filter=EagerLoadingPerformanceTest`

**Rollback**: Simple git revert (no data changes)

---

### Phase 2: Extract TransactionServices (Weeks 2-3)

**Objective**: Decompose TransactionService into focused domain services

**Strategy**: Gradual extraction with backward compatibility wrappers

#### Task 2.1: Extract TransactionHoldService

**Files to create**:
- `app/Services/Transaction/TransactionHoldService.php`
- `app/Services/Contracts/TransactionHoldServiceInterface.php`

**Code to move**:
- `determineHoldRequired()` method (lines 162-177)
- Hold logic from `createTransaction()` (lines 283-304)
- CDD trigger assembly (lines 242-260)

**Dependencies**: `ComplianceService`, `ThresholdService`, `AuditService`

**Update**: `TransactionService` → inject `TransactionHoldService` and delegate hold checks

**Tests to update**:
- `tests/Unit/TransactionServicePreValidationTest.php`
- `tests/Unit/TransactionServiceTest.php`

**Acceptance**: All existing tests pass, new service has unit tests

---

#### Task 2.2: Extract TransactionIdempotencyService

**Files to create**:
- `app/Services/Transaction/TransactionIdempotencyService.php`
- `app/Services/Contracts/TransactionIdempotencyServiceInterface.php`

**Code to move**:
- Idempotency check from `createTransaction()` (lines 311-316)
- Recent duplicate detection (lines 318-341)

**Dependencies**: Eloquent `Transaction` model only

**Note**: This is a pure service, easy extraction

**Acceptance**: Duplicate detection logic tested in isolation

---

#### Task 2.3: Extract TransactionStatusService

**Files to create**:
- `app/Services/Transaction/TransactionStatusService.php`
- `app/Services/Contracts/TransactionStatusServiceInterface.php`

**Code to move**:
- `isRefundable()` (lines 868-892)
- `isCancelled()` (lines 902-905)

**Dependencies**: None (pure model queries)

**Acceptance**: Simple delegation, zero breaking changes

---

#### Task 2.4: Extract TransactionValidationService

**Files to create**:
- `app/Services/Transaction/TransactionValidationService.php`
- `app/Services/Contracts/TransactionValidationServiceInterface.php`

**Code to move** (from `TransactionService`):
- `preValidate()` method (76-118) **minus** the sanctions check call
- `checkSanctions()` (124-149) → consider moving to `CustomerScreeningService` instead
- `isReturningCustomer()` (154-157)
- `determineHoldRequired()` call → delegate to `TransactionHoldService`
- Audit logging logic

**Complexity**: Medium - has multiple dependencies but cohesive

**Integration**:
- Inject `TransactionHoldService` for hold checks
- Keep `CustomerScreeningService` dependency for sanctions
- Keep `HistoricalRiskAnalysisService` for returning customers
- Keep `AuditService` for logging

**Testing**:
- Create `tests/Unit/Transaction/TransactionValidationServiceTest.php`
- Validate preCheck flows: sanctions block, CDD levels, risk flags, hold determination

**Acceptance**: `preValidate()` method in TransactionService becomes single-line delegation

---

#### Task 2.5: Extract TransactionCreationService (Most Complex)

**Files to create**:
- `app/Services/Transaction/TransactionCreationService.php`
- `app/Services/Contracts/TransactionCreationServiceInterface.php`

**Code to move**:
- Core logic of `createTransaction()` (lines 188-448) **minus** pre-validation
- `verifyTillIsOpen()` (457-462)
- `updateTillBalance()` (469-520)
- `createAccountingEntries()` (526-548)
- `createDeferredAccountingEntries()` (554-557)
- `createImmediateAccountingEntries()` (562-565)

**Dependencies** (14 services, reduce to ~8):
- `TransactionValidationService` (pre-checks)
- `CurrencyPositionService` (positions, stock)
- `TransactionAccountingService` (bookkeeping)
- `AuditService` (logging)
- `TellerAllocationService` (teller limits)
- `CacheTagsService` (dashboard cache)
- `MathService` (currency math)
- `TransactionHoldService` (hold status - though mostly determined in validation)

**Refactoring Steps**:

1. Create `TransactionCreationContext` DTO to pass validated data + computed values:
```php
class TransactionCreationContext
{
    public TransactionData $data;
    public Customer $customer;
    public TillBalance $tillBalance;
    public CddLevel $cddLevel;
    public bool $holdRequired;
    public ?TellerAllocation $allocation;
    // ... getters/setters
}
```

2. Move `createTransaction()` body to `TransactionCreationService::create()`
3. Extract private methods:
   - `acquirePositionLock()`
   - `createTransactionRecord()`
   - `reserveStockIfPending()`
   - `processCompletedTransaction()`
   - `updateTellerAllocation()`
   - `dispatchCreationEvent()`

4. Handle edge cases:
   - Duplicate detection (but idempotency already extracted)
   - Insufficient stock (sell transactions)
   - Till closed between validation and creation
   - Concurrent position updates (pessimistic locks)

**Testing**:
- Unit tests for `TransactionCreationService` with mocks
- Feature tests remain unchanged (via TransactionService facade)

**Acceptance**: `createTransaction()` in `TransactionService` becomes:
```php
public function createTransaction(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
{
    $customer = Customer::findOrFail($data['customer_id']);
    $validation = $this->validationService->validatePreTransaction($customer, $data['amount_foreign'], $data['currency_code']);
    $context = $this->buildCreationContext($data, $customer, $validation);
    return $this->creationService->create($context, $userId, $ipAddress);
}
```

---

#### Task 2.6: Extract TransactionApprovalService (Most Complex)

**Files to create**:
- `app/Services/Transaction/TransactionApprovalService.php`
- `app/Services/Contracts/TransactionApprovalServiceInterface.php`

**Code to move**:
- Entire `approveTransaction()` method (lines 581-854)

**Dependencies**:
- `TransactionMonitoringService` (AML flags)
- `CurrencyPositionService` (position updates, stock consumption)
- `TransactionAccountingService` (journal entries)
- `AuditService` (logging)
- `CacheTagsService` (dashboard invalidation)
- `TellerAllocationService` (allocation updates)

**Refactoring Steps**:

1. Create `TransactionApprovalContext` DTO:
```php
class TransactionApprovalContext
{
    public Transaction $transaction;
    public int $approverId;
    public ?string $ipAddress;
    public array $amlResult;
    // ... getters/setters
}
```

2. Move approval logic to `TransactionApprovalService::approve()`
3. Extract private methods:
   - `validateApprovalPreconditions()` (status check, edge cases)
   - `acquireLockAndCheckVersion()` (pessimistic lock + optimistic check)
   - `verifyPreApprovalState()` (customer exists, till open, position exists)
   - `recordStatusTransition()` (history building)
   - `executeSideEffects()` (position, till, allocation, accounting)
   - `postApprovalCleanup()` (events, cache invalidation)
   - `handleApprovalErrors()` (exception handling)

4. Keep the nested transaction structure (DB::transaction)

**Testing**:
- Unit test each private method via public `approve()` interface
- Test edge cases: stale data, insufficient stock, expired reservation, closed till
- Feature tests already cover approval workflow

**Acceptance**: TransactionService `approveTransaction()` becomes:
```php
public function approveTransaction(Transaction $transaction, int $approverId, ?string $ipAddress = null): array
{
    return $this->approvalService->approve($transaction, $approverId, $ipAddress);
}
```

---

#### Task 2.7: Refactor TransactionService to Facade

After all services extracted:

1. **Minimize dependencies**: Keep only the 6 extracted services + minimal shared dependencies
2. **Remove private methods** (moved to services)
3. **Convert public methods** to single-line delegations:
```php
public function preValidate(...): PreValidationResult
{
    return $this->validationService->validatePreTransaction(...);
}

public function createTransaction(...): Transaction
{
    // Orchestrate: validation → creation
    $validation = $this->validationService->validatePreTransaction(...);
    return $this->creationService->create(...);
}

public function approveTransaction(...): array
{
    return $this->approvalService->approve(...);
}
```

4. **Update constructor** to inject only the 6 services + config:
```php
public function __construct(
    protected TransactionValidationService $validationService,
    protected TransactionCreationService $creationService,
    protected TransactionApprovalService $approvalService,
    protected TransactionHoldService $holdService,
    protected TransactionIdempotencyService $idempotencyService,
    protected TransactionStatusService $statusService
) {}
```

5. **Keep implementing `TransactionServiceInterface`** for backward compatibility

---

#### Task 2.8: Update All Callers (Gradual Migration)

**Callers to update** (from impact analysis):

**Controllers** (6 files):
1. `app/Http/Controllers/TransactionWizardController.php`
2. `app/Http/Controllers/TransactionController.php`
3. `app/Http/Controllers/Transaction/TransactionApprovalController.php`
4. `app/Http/Controllers/Api/V1/TransactionController.php`
5. `app/Http/Controllers/CustomerController.php` (quickCreate uses validation indirectly)

**Update Strategy**: Each controller can now inject specific services directly:

**Before**:
```php
class TransactionController
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}
}
```

**After**:
```php
class TransactionController
{
    public function __construct(
        protected TransactionCreationService $creationService,
        protected TransactionValidationService $validationService
    ) {}
    
    public function store(TransactionRequest $request)
    {
        $validation = $this->validationService->validatePreTransaction(...);
        $transaction = $this->creationService->create(...);
        // ...
    }
}
```

**Gradual Migration**:
- Keep facade active for other controllers during transition
- Update one controller at a time, run tests
- After all controllers updated, TransactionService is pure facade

**Tests** (5 files):
- Update to inject new services OR keep using TransactionService (facade)
- Recommendation: Keep tests using TransactionService interface to test orchestration
- Add unit tests for each new service separately

---

### Phase 3: Orphaned Code Cleanup (Week 4)

#### Task 3.1: Identify Orphaned Views

From `scripts/find-orphaned-views.php` output, 25 candidates:

**Action Plan**:
1. For each orphaned view, run: `rg -l "view('{view.name}')" --type php`
2. If no references found, check for dynamic view names: `rg -l "{view.name}" --type php`
3. Verify not referenced in tests
4. Move to `resources/views/orphaned/` for 30-day retention period
5. After 30 days with no usage, delete permanently

**High-confidence orphans to remove**:
- `emails.transaction-approved` (check `Mail::to()->send()` calls)
- `reports.eod-reconciliation` (verify report generation)
- `transactions.receipt` (check receipt printing)
- Component views that are duplicated or replaced

**Testing**: After removal, run: `php artisan view:clear && php artisan test`

---

#### Task 3.2: Fix Route Inconsistencies

**Issues**:
1. **4 unnamed routes**: `broadcasting/auth`, `login`, `test/query-log`, `up`
   - Add `->name('...')` to all routes
   - `broadcasting/auth` → `broadcasting.auth`
   - `test/query-log` → `test.query-log` or `test.query_log`
   - `up` is Laravel's health check - leave unnamed or `up`

2. **Mixed naming patterns**: Standardize to kebab-case for route names in all categories
   - Currently 340 dot-notation, 7 kebab-case, 4 unknown
   - Convert all to kebab-case: `accounting.balance-sheet` not `accounting.balanceSheet`

3. **Inline middleware**: Convert 349 routes to use named middleware aliases
   - Already have good aliases defined in `bootstrap/app.php`
   - Replace inline class names with alias: `'middleware' => ['auth', 'role:manager']`

**Implementation**:
- Use `sed` or manual edit to update `routes/*.php` files
- Update route() calls in controllers/blade if names change
- Run `php artisan route:list` to verify

---

#### Task 3.3: Remove TODO/DEPRECATED Code

From grep results:
- `app/Services/Reporting/ReportingService.php:259` - `XXX` comment
- `app/Models/JournalEntry.php:24` - `@property` PHPDoc placeholder
- `app/Http/Requests/*CustomerRequest.php` - ID format validation comments (keep)
- `app/Http/Controllers/CustomerController.php:405,411` - validation comments (keep)

**Action**: Remove only `XXX` markers; keep legitimate PHPDoc and validation hints.

---

### Phase 4: Code Quality Improvements (Week 5)

#### Task 4.1: Standardize Exception Handling

**Problem**: Generic `\Exception` catches in some controllers

**Solution**:
1. Create domain-specific exceptions:
   - `TransactionException` (base)
   - `TransactionValidationException`
   - `TransactionCreationException`
   - `TransactionApprovalException`
   - `AllocationException` (already exists)

2. Update `TransactionService` catch blocks to throw typed exceptions
3. Update global exception handler (`app/Exceptions/Handler.php`) to handle them
4. Return appropriate HTTP status codes:
   - 400 for validation errors
   - 409 for concurrency conflicts
   - 422 for business rule violations
   - 500 for unexpected errors

**Benefit**: Better error responses, easier debugging

---

#### Task 4.2: Consolidate Cache Keys

**Problem**: Hardcoded cache keys scattered (e.g., `'exchange_rates_for_transactions'`)

**Solution**:
1. Create `app/Services/System/CacheKeys.php` enum or constants:
```php
enum CacheKey: string
{
    case ExchangeRates = 'exchange_rates_for_transactions';
    case DashboardStats = 'dashboard_stats';
    case CustomerSearch = 'customer_search_';
    // ...
}
```

2. Replace all string literals: `Cache::remember(CacheKey::ExchangeRates->value, ...)`

**Benefit**: Prevent collisions, easier invalidation

---

#### Task 4.3: Controller Method Length

**Large controllers**:
- `CustomerController@show`: 100+ lines → extract to service or private method
- `RegulatoryReportController@msb2`: 80+ lines → move query logic to ReportingService

**Approach**: Extract private methods first (low risk), evaluate if service extraction needed later

---

### Phase 5: Validation & Deployment (Week 6)

#### Task 5.1: Comprehensive Testing

**Unit Tests**:
```bash
php artisan test --compact --filter=Transaction
php artisan test --compact --filter=Customer
php artisan test --compact --filter=Schema
```

**Feature Tests**:
```bash
php artisan test --compact --filter=TransactionWorkflow
php artisan test --compact --filter=TransactionAccountingVerification
```

**Architecture Tests** (already exist):
```bash
php artisan test --compact --filter=ModelHierarchy
php artisan test --compact --filter=EagerLoadingPerformance
```

**Pint Code Style**:
```bash
vendor/bin/pint --format agent
```

---

#### Task 5.2: GitNexus Change Detection

Run before commit:
```bash
npx gitnexus detect_changes()
```

Verify:
- Only expected symbols affected
- No unintended ripple effects
- Risk assessment matches expectations

---

#### Task 5.3: Database Migration Check

Even though no data migrations needed, clear caches:
```bash
php artisan optimize:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

#### Task 5.4: Staging Deployment

1. Merge to `develop` branch
2. Deploy to staging environment
3. Run full test suite on staging
4. Smoke test critical paths:
   - Create customer
   - Create transaction (Buy/Sell)
   - Approve transaction (>= RM 10k)
   - Generate reports (MSB2, LMCA)
   - View dashboard

5. Check logs for errors/exceptions
6. Verify audit trails intact
7. Verify compliance job runs (scheduler)

---

#### Task 5.5: Production Deployment

**Prerequisites**:
- ✅ Staging approved
- ✅ Backup complete (database + files)
- ✅ Rollback plan ready
- ✅ Maintenance window scheduled (if needed)

**Deployment Steps**:
1. Enable maintenance mode: `php artisan down --render="errors::503"`
2. Pull code, install dependencies: `composer install --no-dev --optimize-autoloader`
3. Clear caches (as above)
4. Run migrations (none expected)
5. Restart Horizon: `php artisan horizon:terminate`
6. Restart queues: `supervisorctl restart cems-worker:*`
7. Disable maintenance mode: `php artisan up`
8. Smoke test critical user flows
9. Monitor logs for 30 minutes: `tail -f storage/logs/laravel.log`

**Rollback**:
```bash
git revert HEAD
composer install
php artisan migrate:rollback --pretend  # if any migration ran
php artisan up
```

---

## Testing Strategy

### Unit Tests

**New tests for extracted services**:
- `tests/Unit/Transaction/TransactionHoldServiceTest.php`
- `tests/Unit/Transaction/TransactionIdempotencyServiceTest.php`
- `tests/Unit/Transaction/TransactionStatusServiceTest.php`
- `tests/Unit/Transaction/TransactionValidationServiceTest.php`
- `tests/Unit/Transaction/TransactionCreationServiceTest.php`
- `tests/Unit/Transaction/TransactionApprovalServiceTest.php`

**Coverage goals**:
- 100% of new service methods
- 90%+ overall code coverage maintained

---

### Integration Tests

Existing feature tests validate end-to-end workflows:
- `tests/Feature/TransactionWorkflowTest.php`
- `tests/Feature/TransactionAccountingVerificationTest.php`
- `tests/Feature/Api/TransactionApiTest.php`

These should pass unchanged if refactoring is correct.

---

### Architecture Tests

Existing tests to ensure architecture rules maintained:
- `tests/Unit/Models/ModelHierarchyTest.php` - models extend BaseModel correctly
- `tests/Unit/Models/EagerLoadingPerformanceTest.php` - no default eager loading
- `tests/Feature/Views/ThemeTokenUsageTest.php` - UI consistency
- `tests/Feature/Api/ApiSecurityFixesTest.php` - form request authorization

---

## Rollback Plan

### For Each Phase

**Schema Changes** (Phase 1):
- Rollback: git revert to previous commit
- No data migration, so instant
- Clear caches

**Service Extraction** (Phase 2):
- Keep TransactionService intact until all tested
- If breaking: git revert feature branch
- Tests should catch issues immediately

**Orphaned Code** (Phase 3):
- Restore from git before deletion
- Views can be restored from backup

**General**:
- Always keep `storage/backups/` with daily database backups during deployment
- Use Laravel Horizon's job retry mechanism (no job loss)
- Monitoring: check Horizon dashboard, logs, scheduled tasks

---

## Success Metrics

### Quantitative

1. **Code Complexity**:
   - TransactionService reduced from 906 to <150 lines (facade)
   - Average cyclomatic complexity per service < 10
   - Maximum method length < 50 lines

2. **Test Coverage**:
   - Maintain >90% overall coverage
   - 100% coverage on new services

3. **Orphaned Code**:
   - Remove 25+ unused views
   - Fix 9 schema issues
   - Standardize 351 route names

4. **Performance**:
   - No regression in transaction creation time (< 500ms p95)
   - No increase in DB queries per transaction (average < 20)

---

### Qualitative

1. **Developer Experience**:
   - New developers can understand transaction flow in < 1 hour
   - Services are cohesive (single responsibility)
   - Easy to add new transaction types (e.g., crypto)

2. **Maintainability**:
   - Bug fixes localized to specific services
   - Changes to validation don't require touching approval logic
   - Compliance audit easier (clear separation)

3. **Regulatory Compliance**:
   - Audit logs unchanged
   - Transaction history transitions identical
   - No compliance logic removed, only reorganized

---

## Dependencies & Assumptions

### External Dependencies
- GitNexus index up-to-date (run `analyze` before starting)
- All team members on same PHP/Laravel versions
- Staging environment mirrors production

### Assumptions
- No pending regulatory changes in next 6 months
- Database schema stable (no new columns planned)
- All tests passing at baseline
- Developers familiar with domain-driven design patterns

---

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Breaking transaction processing | Low | Critical | Extensive testing, gradual rollout, feature flags |
| Compliance audit failure | Low | Critical | Keep audit logic unchanged, verify logs |
| Schema change causes errors | Medium | High | Use `$casts` only (no destructive changes), clear cache |
| Orphaned view removal breaks UI | Medium | Medium | Move to orphaned/ folder first, 30-day retention |
| Developer resistance to change | Low | Medium | Training, documentation, code review involvement |
| Timeline overrun | Medium | Medium | Phase approach allows stopping at any point |

---

## Timeline

| Phase | Duration | Parallel? | Dependencies |
|-------|----------|-----------|--------------|
| Phase 0: Preparation | 1 day | No | - |
| Phase 1: Schema fixes | 2 days | Yes | Phase 0 complete |
| Phase 2: Service extraction | 2 weeks | Yes | Phase 0 complete (Phase 1 can run parallel) |
| Phase 3: Orphaned cleanup | 1 week | Yes | Independent |
| Phase 4: Quality improvements | 1 week | No | After Phase 2 (service extraction stable) |
| Phase 5: Validation & deploy | 1 week | No | All previous phases complete |

**Total**: 4-6 weeks (with parallelization)

---

## Conclusion

This plan provides a structured, low-risk approach to improving the architecture of a critical financial compliance application. By extracting the monolithic `TransactionService` into focused domain services, we improve maintainability without disrupting regulatory compliance or risking data integrity.

The phased approach allows stopping at any point with a working system. Each phase delivers value independently, and the comprehensive testing strategy ensures confidence in the changes.

**Recommended Next Steps**:
1. Review this plan with the team
2. Get approval from compliance/risk stakeholders
3. Create Jira/GitHub issues for each task
4. Assign Phase 2 (service extraction) to senior developer(s)
5. Begin Phase 0 preparation immediately

---

## Appendix

### A. Files to Modify

**Phase 1** (Schema):
- `app/Models/Customer.php`
- `app/Models/User.php`
- `app/Models/Transaction.php`
- `app/Models/CurrencyPosition.php`

**Phase 2** (Services):
- Create 6 new service classes + interfaces
- Modify: `TransactionService.php`
- Modify: `AppServiceProvider.php` (bindings if needed)
- Update: 6 controller files
- Update: 5 test files

**Phase 3** (Orphans):
- Delete/move 25 view files
- Modify: `routes/web.php`, `routes/api_v1.php`
- Remove: `scripts/find-orphaned-routes.php` (not exist, create if needed)

**Phase 4** (Quality):
- Create: `app/Services/System/CacheKeys.php`
- Update: various controllers/services
- Modify: `app/Exceptions/Handler.php`

---

### B. TransactionService Decomposition Diagram

```
Before (906 lines)
└── TransactionService (monolith)
    ├── preValidate() + sanctions, CDD, hold
    ├── createTransaction() + 14 dependencies
    │   ├── validation
    │   ├── position locking
    │   ├── stock reservation
    │   ├── till updates
    │   ├── allocation updates
    │   ├── accounting
    │   └── events
    ├── approveTransaction() + AML checks, locks, side effects
    ├── updateTillBalance()
    ├── createAccountingEntries()
    └── isRefundable(), isCancelled()

After (Facade + 6 services)
└── TransactionService (150 lines - coordinator)
    ├── TransactionValidationService
    │   └── preValidate(), sanctions(), hold logic
    ├── TransactionCreationService
    │   └── create(), position ops, till updates, allocation, accounting
    ├── TransactionApprovalService
    │   └── approve(), AML, locks, side effects, events
    ├── TransactionHoldService
    │   └── requiresHold(), getHoldReasons()
    ├── TransactionIdempotencyService
    │   └── findDuplicate(), checkRecentDuplicate()
    └── TransactionStatusService
        └── isRefundable(), isCancelled(), canBeApproved()
```

---

### C. Impact Analysis Summary

**TransactionService direct callers** (16):
- Controllers: 6
- Tests: 5
- Job: 1
- Provider: 1
- Others: 3

**No external API consumers** (all internal)

**Risk assessment**: HIGH but manageable with facade pattern

---

**Document Version**: 1.0  
**Status**: Draft for Review  
**Author**: Code Review AI Assistant  
**Review Date**: 2025-07-09
