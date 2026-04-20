# MVC Compliance Gap Analysis

**Date:** 2026-04-20
**Project:** CEMS-MY (Currency Exchange Management System)
**Analysis Scope:** Controllers, Models, Services, Routes

---

## Executive Summary

The CEMS-MY codebase demonstrates **good overall MVC architecture** with proper separation of concerns in most areas. However, there are **several MVC violations** that should be addressed to improve maintainability, testability, and adherence to best practices.

### Key Findings

| Category | Status | Severity | Count |
|----------|--------|----------|-------|
| Controllers with Business Logic | ⚠️ Needs Improvement | Medium | 3 |
| Models with Business Logic | ⚠️ Needs Improvement | Medium | 8 |
| Route Organization | ✅ Good | Low | 0 |
| Service Layer | ✅ Excellent | N/A | N/A |

---

## 1. Controllers with Business Logic

### 1.1 CustomerController.php (561 lines)

**Issue:** Controller contains business logic that should be in services.

**Location:** `app/Http/Controllers/CustomerController.php:142-278`

**Problems:**
- Encryption logic in `store()` method (lines 172-181)
- Sanction screening logic (lines 202-225)
- Risk assessment logic (lines 227-228)
- Direct database operations without service abstraction

**Current Code:**
```php
public function store(Request $request)
{
    $validated = $request->validate([...]);

    DB::beginTransaction();
    try {
        // Encryption logic (should be in service)
        $encryptedIdNumber = $this->encryptionService->encrypt($validated['id_number']);
        $encryptedAddress = ! empty($validated['address'])
            ? $this->encryptionService->encrypt($validated['address'])
            : null;

        // Sanction screening (should be in service)
        $sanctionMatches = $this->sanctionService->screenName($validated['full_name']);
        $hasSanctionHit = ! empty($sanctionMatches);

        if ($hasSanctionHit) {
            $customer->update([...]);
            SystemLog::create([...]); // Direct DB operation
        }

        // Risk assessment (should be in service)
        $this->riskScoringEngine->recalculateForCustomer($customer->id);
    }
}
```

**Recommendation:**
Create `CustomerService` to handle all customer-related business logic:

```php
class CustomerService
{
    public function createCustomer(array $data, int $userId): Customer
    {
        return DB::transaction(function () use ($data, $userId) {
            // Encrypt sensitive fields
            $encryptedData = $this->encryptCustomerData($data);

            // Create customer
            $customer = Customer::create($encryptedData);

            // Screen against sanctions
            $this->screenCustomer($customer);

            // Calculate risk score
            $this->calculateRiskScore($customer);

            // Log creation
            $this->auditService->logCustomer('customer_created', $customer->id, [...]);

            return $customer;
        });
    }

    protected function encryptCustomerData(array $data): array
    {
        // Encryption logic
    }

    protected function screenCustomer(Customer $customer): void
    {
        // Sanction screening logic
    }

    protected function calculateRiskScore(Customer $customer): void
    {
        // Risk assessment logic
    }
}
```

**Controller becomes:**
```php
public function store(Request $request)
{
    $validated = $request->validate([...]);

    $customer = $this->customerService->createCustomer($validated, auth()->id());

    return redirect()->route('customers.show', $customer)
        ->with('success', 'Customer created successfully');
}
```

---

### 1.2 TransactionController.php (211 lines)

**Issue:** Controller contains accounting logic that should be in services.

**Location:** `app/Http/Controllers/TransactionController.php:81-150`

**Problems:**
- Direct use of `TransactionAccounting` trait in controller
- Accounting logic mixed with HTTP concerns

**Recommendation:**
Move all accounting logic to `AccountingService` and remove trait from controller.

---

### 1.3 UserController.php (315 lines)

**Issue:** Controller contains user management business logic.

**Location:** `app/Http/Controllers/UserController.php`

**Problems:**
- Password hashing logic in controller
- Role assignment logic in controller
- MFA setup logic in controller

**Recommendation:**
Create `UserService` to handle all user-related business logic.

---

## 2. Models with Business Logic

### 2.1 Transaction.php (276 lines)

**Issue:** Model contains business logic methods.

**Location:** `app/Models/Transaction.php:199-223`

**Problems:**
- `isRefundable()` method contains business logic (lines 199-223)
- `isCancelled()` method (lines 232-235)

**Current Code:**
```php
public function isRefundable(): bool
{
    // Must be completed
    if (! $this->status->isCompleted()) {
        return false;
    }

    // Cannot be already cancelled
    if ($this->cancelled_at !== null) {
        return false;
    }

    // Must be within configured cancellation window
    $cancellationWindowHours = config('cems.transaction_cancellation_window_hours', 24);
    if ($this->created_at->diffInHours(now()) >= $cancellationWindowHours) {
        return false;
    }

    // Cannot be a refund
    if ($this->is_refund) {
        return false;
    }

    return true;
}
```

**Recommendation:**
Move to `TransactionService`:

```php
class TransactionService
{
    public function isRefundable(Transaction $transaction): bool
    {
        // Business logic here
    }
}
```

**Model should only contain:**
- Relationships
- Accessors/Mutators for data formatting
- Scopes for query building
- No business logic

---

### 2.2 Customer.php (247 lines)

**Issue:** Model contains multiple business logic methods.

**Location:** `app/Models/Customer.php:177-246`

**Problems:**
- `isPepAssociate()` method (lines 177-180)
- `isHighRisk()` method (lines 190-193)
- `computeBlindIndex()` method (lines 231-236)
- `findByIdNumber()` method (lines 241-246)

**Current Code:**
```php
public function isPepAssociate(): bool
{
    return $this->pepRelations()->where('is_pep', true)->exists();
}

public function isHighRisk(): bool
{
    return $this->risk_rating === 'High' || $this->pep_status || $this->sanction_hit;
}

public static function computeBlindIndex(string $plaintext): string
{
    $key = config('app.key');
    return hash_hmac('sha256', $plaintext, $key);
}

public static function findByIdNumber(string $idNumber): ?self
{
    $hash = self::computeBlindIndex($idNumber);
    return static::where('id_number_hash', $hash)->first();
}
```

**Recommendation:**
Move to `CustomerService`:

```php
class CustomerService
{
    public function isPepAssociate(Customer $customer): bool
    {
        return $customer->pepRelations()->where('is_pep', true)->exists();
    }

    public function isHighRisk(Customer $customer): bool
    {
        return $customer->risk_rating === 'High'
            || $customer->pep_status
            || $customer->sanction_hit;
    }

    public function computeBlindIndex(string $plaintext): string
    {
        $key = config('app.key');
        return hash_hmac('sha256', $plaintext, $key);
    }

    public function findByIdNumber(string $idNumber): ?Customer
    {
        $hash = $this->computeBlindIndex($idNumber);
        return Customer::where('id_number_hash', $hash)->first();
    }
}
```

---

### 2.3 AccountingPeriod.php

**Issue:** Model contains business logic methods.

**Problems:**
- `isOpen()` method
- `isClosed()` method

**Recommendation:**
Move to `AccountingService`.

---

### 2.4 Alert.php

**Issue:** Model contains business logic methods.

**Problems:**
- `isOverdue()` method
- `isResolved()` method

**Recommendation:**
Move to `AlertService`.

---

### 2.5 ApprovalTask.php

**Issue:** Model contains multiple business logic methods.

**Problems:**
- `isPending()` method
- `isApproved()` method
- `isRejected()` method
- `isExpired()` method
- `isActionable()` method

**Recommendation:**
Move to `ApprovalTaskService`.

---

### 2.6 ChartOfAccount.php

**Issue:** Model contains business logic methods.

**Problems:**
- `isAsset()` method
- `isLiability()` method
- `isEquity()` method
- `isRevenue()` method
- `isExpense()` method

**Recommendation:**
Move to `AccountingService`.

---

### 2.7 CounterSession.php

**Issue:** Model contains business logic methods.

**Problems:**
- `isOpen()` method
- `isClosed()` method
- `isHandedOver()` method

**Recommendation:**
Move to `CounterService`.

---

### 2.8 CustomerDocument.php

**Issue:** Model contains business logic methods.

**Problems:**
- `isVerified()` method
- `isExpired()` method
- `isExpiringSoon()` method

**Recommendation:**
Move to `CustomerDocumentService`.

---

## 3. Route Organization

### 3.1 web.php (551 lines)

**Status:** ✅ **Good**

**Analysis:**
- Well-organized with clear sections
- Proper use of route groups
- Good naming conventions
- Appropriate middleware usage

**Recommendations:**
- Consider splitting into smaller files by feature (optional)
- Current organization is acceptable

---

### 3.2 api.php (179 lines)

**Status:** ✅ **Good**

**Analysis:**
- Well-organized
- Proper API versioning
- Good use of route groups

**Recommendations:**
- None needed

---

### 3.3 api_v1.php (218 lines)

**Status:** ✅ **Good**

**Analysis:**
- Well-organized
- Proper API structure
- Good use of resource controllers

**Recommendations:**
- None needed

---

## 4. Service Layer

### 4.1 Overall Assessment

**Status:** ✅ **Excellent**

**Strengths:**
- Comprehensive service layer with 83 services
- Proper dependency injection
- Good separation of concerns
- Well-documented services

**Services Present:**
- TransactionService
- ComplianceService
- CurrencyPositionService
- AccountingService
- CounterService
- CustomerRiskScoringService
- TransactionMonitoringService
- MetricsService
- And 75+ more

**Recommendations:**
- Continue using services for all business logic
- Ensure controllers only handle HTTP concerns

---

## 5. Summary of Violations

### 5.1 Controllers with Business Logic (3)

| Controller | Lines | Issue | Priority |
|------------|-------|-------|----------|
| CustomerController | 561 | Encryption, screening, risk logic | High |
| TransactionController | 211 | Accounting logic | Medium |
| UserController | 315 | User management logic | Medium |

### 5.2 Models with Business Logic (8)

| Model | Methods | Issue | Priority |
|-------|---------|-------|----------|
| Transaction | 2 | Refundability, cancellation logic | High |
| Customer | 4 | PEP, risk, blind index logic | High |
| AccountingPeriod | 2 | Period status logic | Medium |
| Alert | 2 | Alert status logic | Medium |
| ApprovalTask | 5 | Task status logic | Medium |
| ChartOfAccount | 5 | Account type logic | Low |
| CounterSession | 3 | Session status logic | Medium |
| CustomerDocument | 3 | Document status logic | Low |

---

## 6. Recommendations

### 6.1 Immediate Actions (High Priority)

1. **Create CustomerService**
   - Move encryption logic from CustomerController
   - Move sanction screening logic
   - Move risk assessment logic
   - Move `isPepAssociate()`, `isHighRisk()`, `computeBlindIndex()`, `findByIdNumber()` from Customer model

2. **Refactor TransactionController**
   - Remove TransactionAccounting trait
   - Move all accounting logic to AccountingService
   - Move `isRefundable()` from Transaction model to TransactionService

3. **Create UserService**
   - Move password hashing logic
   - Move role assignment logic
   - Move MFA setup logic

### 6.2 Short-term Actions (Medium Priority)

4. **Create AlertService**
   - Move `isOverdue()`, `isResolved()` from Alert model

5. **Create ApprovalTaskService**
   - Move all status methods from ApprovalTask model

6. **Create CounterSessionService**
   - Move `isOpen()`, `isClosed()`, `isHandedOver()` from CounterSession model

7. **Create CustomerDocumentService**
   - Move `isVerified()`, `isExpired()`, `isExpiringSoon()` from CustomerDocument model

### 6.3 Long-term Actions (Low Priority)

8. **Refactor AccountingPeriod**
   - Move `isOpen()`, `isClosed()` to AccountingService

9. **Refactor ChartOfAccount**
   - Move account type methods to AccountingService

10. **Consider Route Splitting**
    - Split web.php into smaller files by feature (optional)

---

## 7. Best Practices Checklist

### Controllers ✅
- [x] Thin controllers (most are)
- [x] Dependency injection
- [x] Request validation
- [ ] No business logic (3 violations)
- [x] Proper HTTP response handling

### Models ⚠️
- [x] Relationships defined
- [x] Accessors/Mutators for formatting
- [x] Scopes for queries
- [ ] No business logic (8 violations)
- [x] Proper casting

### Services ✅
- [x] Comprehensive service layer
- [x] Dependency injection
- [x] Single responsibility
- [x] Well-documented
- [x] Testable

### Routes ✅
- [x] Well-organized
- [x] Proper grouping
- [x] Good naming
- [x] Appropriate middleware
- [x] API versioning

---

## 8. Conclusion

The CEMS-MY codebase demonstrates **strong MVC architecture** with a comprehensive service layer and proper separation of concerns in most areas. However, there are **11 MVC violations** (3 controllers + 8 models) that should be addressed to improve maintainability and adherence to best practices.

### Overall Grade: B+

**Strengths:**
- Excellent service layer
- Good controller organization
- Proper dependency injection
- Well-documented code

**Areas for Improvement:**
- Remove business logic from controllers (3 violations)
- Remove business logic from models (8 violations)
- Create additional services for missing abstractions

### Estimated Effort

- **High Priority:** 2-3 days
- **Medium Priority:** 3-4 days
- **Low Priority:** 1-2 days

**Total Estimated Effort:** 6-9 days

---

## 9. References

- Laravel Best Practices: https://github.com/alexeymezenin/laravel-best-practices
- MVC Pattern: https://en.wikipedia.org/wiki/Model%E2%80%93view%E2%80%93controller
- Laravel Service Layer: https://laravel.com/docs/10.x/services
- Clean Code Principles: https://github.com/ryanmcdermott/clean-code-php
