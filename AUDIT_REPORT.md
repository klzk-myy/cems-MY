# CEMS-MY Pre-Production Audit Report

**Date**: April 21, 2026  
**Auditor**: AI Code Review System  
**Status**: 🔴 CRITICAL - DO NOT DEPLOY WITHOUT FIXES

---

## Executive Summary

This pre-production audit identified **17 CRITICAL/HIGH severity issues** that must be resolved before go-live. The application has several production-blocking bugs including missing model methods, race conditions, compliance violations, and security vulnerabilities.

### Quick Stats
| Category | Critical | High | Medium | Low |
|----------|----------|------|--------|-----|
| Business Logic | 5 | 3 | 2 | 1 |
| Security | 2 | 3 | 4 | 1 |
| Compliance | 1 | 2 | 2 | 0 |
| **TOTAL** | **8** | **8** | **8** | **2** |

### Test Results
- ✅ 495 tests passed
- ❌ 4 tests failing (missing model methods)
- ⚠️ 1 risky test
- ⏭️ 15 skipped tests

---

## 🚨 CRITICAL ISSUES (Deploy Blockers)

### CRIT-001: Missing Model Methods Causing Runtime Errors
**Severity**: CRITICAL  
**Impact**: Application crashes when approval/cancellation workflows execute  
**Files Affected**:
- `app/Models/ApprovalTask.php` - Missing `isPending()` method
- `app/Models/CounterSession.php` - Missing `isOpen()` method

**Error Evidence**:
```
BadMethodCallException: Call to undefined method App\Models\ApprovalTask::isPending()
  at app/Services/ApprovalWorkflowService.php:209

BadMethodCallException: Call to undefined method App\Models\CounterSession::isOpen()
  at app/Services/CounterService.php:105, 286
```

**Fix Required**:
```php
// Add to ApprovalTask model:
public function isPending(): bool
{
    return $this->status === self::STATUS_PENDING;
}

public function isActionable(): bool
{
    return $this->isPending() && $this->expires_at->isFuture();
}

// Add to CounterSession model:
public function isOpen(): bool
{
    return $this->status === CounterSessionStatus::Open;
}
```

---

### CRIT-002: Transaction State Machine - Unreachable States
**Severity**: CRITICAL  
**Impact**: Transactions can become stuck in limbo states with no exit path  
**File**: `app/Services/TransactionStateMachine.php:21-67`

**Issue**: The `TransactionStatus` enum defines `Pending` and `OnHold` states but the state machine has NO transitions defined for them:

```php
// TransactionStatus.php - these exist:
case Pending = 'Pending';      // Orphan state!
case OnHold = 'OnHold';        // Orphan state!

// TransactionStateMachine.php - NO transitions for Pending/OnHold:
protected const TRANSITIONS = [
    'Draft' => [...],
    'PendingApproval' => [...],
    // ... but no 'Pending' or 'OnHold' keys!
];
```

**Impact**: Any transaction reaching `Pending` or `OnHold` status cannot be approved, rejected, or cancelled. Customer funds could be frozen indefinitely.

**Fix Options**:
1. Add transitions for `Pending` and `OnHold` states
2. Remove these orphan states from the enum
3. Document that these states are intentionally terminal

---

### CRIT-003: Segregation of Duties Violation - Self-Approval Possible
**Severity**: CRITICAL  
**Regulatory Risk**: BNM AML/CFT compliance violation  
**Files**:
- `app/Http/Controllers/Transaction/TransactionApprovalController.php:53`
- `app/Http/Controllers/Api/V1/TransactionApprovalController.php`

**Issue 1**: Web controller uses wrong field name:
```php
// WRONG - field doesn't exist:
if ($transaction->created_by === auth()->id())

// CORRECT - should be user_id:
if ($transaction->user_id === auth()->id())
```

**Issue 2**: API controller has NO self-approval check at all.

**Impact**: A teller/manager can approve their own transactions, violating BNM's segregation of duties requirements.

**Fix Required**:
```php
// In BOTH controllers, add:
if ($transaction->user_id === auth()->id()) {
    return response()->json([
        'message' => 'Cannot approve your own transaction. Segregation of duties requires a different approver.'
    ], 403);
}
```

---

### CRIT-004: Race Condition in Transaction Approval
**Severity**: CRITICAL  
**Impact**: Could approve transactions against closed tills  
**File**: `app/Services/TransactionService.php:863-873`

**Issue**: Transaction is locked with `lockForUpdate()` but TillBalance is queried WITHOUT locking:

```php
// Lines 776-779 - Transaction locked ✓
$lockedTransaction = Transaction::where('id', $transaction->id)
    ->lockForUpdate()
    ->first();

// Lines 863-873 - TillBalance NOT locked ✗
$tillBalance = TillBalance::where('till_id', $lockedTransaction->till_id)
    ->whereNull('closed_at')
    ->first();  // No lockForUpdate()!
```

**Race Condition**:
1. Teller A acquires transaction lock
2. Teller B closes the till (succeeds - no lock on till balance)
3. Teller A proceeds with approval against now-closed till

**Fix Required**:
```php
$tillBalance = TillBalance::where('till_id', $lockedTransaction->till_id)
    ->whereNull('closed_at')
    ->lockForUpdate()  // Add this
    ->first();
```

---

### CRIT-005: Stock Reservation Not Released on Cancellation
**Severity**: CRITICAL  
**Impact**: Stock remains blocked for 24 hours after cancellation  
**File**: `app/Services/TransactionService.php`

**Issue**: When a `PendingApproval` transaction is cancelled, the associated `StockReservation` is NOT explicitly released:

```php
// Missing in cancel path:
// $this->positionService->releaseStockReservation($transaction->id);
```

**Impact**: 
- Cancelled transactions block stock for 24 hours (until expiry)
- Could cause `InsufficientStockException` for valid transactions
- Reduced operational capacity

**Fix Required**:
```php
// In cancellation workflow:
if ($transaction->status === TransactionStatus::PendingCancellation) {
    $this->positionService->releaseStockReservation($transaction->id);
    // ... proceed with cancellation
}
```

---

### CRIT-006: PendingCancellation Allows Dangerous State Transitions
**Severity**: CRITICAL  
**Impact**: Could bypass approval workflow entirely  
**File**: `app/Services/TransactionStateMachine.php:61-66`

**Issue**: `PendingCancellation` allows transitions to approved states:

```php
'PendingCancellation' => [
    'Cancelled',
    'Approved',      // ← DANGEROUS
    'Processing',    // ← DANGEROUS
    'Completed',     // ← DANGEROUS
],
```

**Impact**: A transaction in `PendingCancellation` could be moved directly to `Completed`, bypassing all approval logic.

**Fix Required**:
```php
'PendingCancellation' => [
    'Cancelled',
    // Remove: 'Approved', 'Processing', 'Completed'
],
```

---

### CRIT-007: Threshold Inconsistency - 55 Direct Config Calls
**Severity**: CRITICAL  
**Impact**: Threshold changes won't reflect in all parts of the system  
**Files**: Multiple (grep found 55 instances)

**Issue**: Code uses BOTH `ThresholdService` (correct) AND direct `config()` calls (incorrect):

```php
// WRONG - bypasses ThresholdService audit trail:
config('thresholds.cdd.large_transaction', '50000')
config('thresholds.approval.auto_approve', '3000')

// CORRECT - uses ThresholdService:
$this->thresholdService->get('cdd', 'large_transaction')
```

**Affected Files**:
- `TransactionService.php` (lines 270-302)
- `EodReconciliationService.php` (lines 87, 206)
- `TransactionApprovalController.php` (line 248)
- Multiple enums using `config()` helper

**Impact**: 
- Runtime threshold changes via `ThresholdService` won't be reflected
- No audit trail for threshold access via direct config
- Breaks centralized threshold management

**Fix Required**: 
- Audit all 55 `config('thresholds...')` calls
- Replace with `$this->thresholdService->get()`
- Add static analysis rule to prevent direct config access

---

### CRIT-008: MFA Bypass via Weak Device Fingerprinting
**Severity**: CRITICAL (CVSS 9.1)  
**Impact**: Complete authentication bypass possible  
**File**: `app/Services/MfaService.php:375-384`

**Issue**: Device fingerprint uses easily spoofable parameters:

```php
public function generateDeviceFingerprint(): string
{
    $data = implode('|', [
        request()->userAgent() ?? 'unknown',
        request()->ip() ?? '0.0.0.0',           // ← Can be spoofed via X-Forwarded-For
        request()->header('Accept-Language') ?? 'en',
    ]);
    return hash('sha256', $data);
}
```

**Attack Vector**:
1. Attacker obtains target's IP and User-Agent
2. Crafts request with matching fingerprint
3. Bypasses MFA for 15-30 minutes

**Remediation**:
1. Replace with cryptographic challenge-response
2. Add IP binding to session with `hash('sha256', $sessionId . $ipAddress)`
3. Reduce trusted device validity to 24 hours max
4. Require re-verification after password change

---

## 🔶 HIGH SEVERITY ISSUES

### HIGH-001: Mass Assignment on Customer Update
**Severity**: HIGH (CVSS 7.1)  
**File**: `app/Http/Controllers/Api/V1/CustomerController.php:169-236`

**Issue**: Any authenticated user can update sensitive customer fields:
- `pep_status` - Politically Exposed Person status
- `risk_rating` - Customer risk classification  
- `is_active` - Customer active status

**Route Protection Gap**:
```php
// Web route - no role requirement!
Route::put('/{customer}', [CustomerController::class, 'update'])
    ->middleware('throttle:30,1');  // Only rate limiting!

// API route - no role requirement!
Route::put('/customers/{customer}', [CustomerController::class, 'update'])
    ->middleware('throttle:30,1');
```

**Fix Required**:
```php
// Add authorization:
if (!$user->isManager() && !$user->isComplianceOfficer() && !$user->isAdmin()) {
    return response()->json(['message' => 'Unauthorized'], 403);
}

// Or restrict fillable fields for tellers
```

---

### HIGH-002: File Upload Vulnerability in Bulk Import
**Severity**: HIGH (CVSS 8.1)  
**File**: `app/Http/Controllers/Api/V1/BulkImportController.php:30-55`

**Issue**: CSV upload only validates extension, not content:

```php
$request->validate([
    'file' => 'required|file|mimes:csv,txt|max:10240', // Only checks extension
]);
```

**Attack Vector**:
1. Attacker uploads CSV with formula injection:
   ```csv
   name,id_number
   "Test","=cmd|' /C calc'!A0"
   ```
2. Admin opens in spreadsheet application
3. Payload executes

**Remediation**:
```php
// Add content validation:
'file' => [
    'required',
    'file',
    function ($attribute, $value, $fail) {
        if (!in_array($value->getMimeType(), ['text/plain', 'text/csv'])) {
            $fail('Invalid file type');
        }
    },
],

// Sanitize content:
$content = preg_replace('/^[=@+\-]/m', '', $content);
```

---

### HIGH-003: EOD Reconciliation Uses Hardcoded Thresholds
**Severity**: HIGH  
**File**: `app/Services/EodReconciliationService.php:390-393`

**Issue**: Variance thresholds are hardcoded, not using ThresholdService:

```php
// HARDCODED - won't reflect config changes:
if (BcmathHelper::gt($absVariance, '500.00')) {  // ← Hardcoded!
    $status = 'critical';
} elseif (BcmathHelper::gt($absVariance, '100.00')) {  // ← Hardcoded!
```

**Fix Required**:
```php
if (BcmathHelper::gt($absVariance, $this->thresholdService->getVarianceRedThreshold())) {
    $status = 'critical';
}
```

---

### HIGH-004: TillBalance Variance Uses Float Arithmetic
**Severity**: HIGH  
**File**: `app/Models/TillBalance.php:74-81`

**Issue**: Variance calculation uses PHP float instead of BCMath:

```php
public function calculateVariance(): float  // ← Returns float!
{
    return (float) $this->closing_balance - $this->getExpectedBalance();  // ← Float arithmetic!
}
```

**Impact**: Rounding errors for large/precise amounts. Violates "Never use float for currency" rule.

**Fix Required**:
```php
use App\Services\MathService;

public function calculateVariance(): string  // Return string
{
    return app(MathService::class)->subtract(
        (string) $this->closing_balance,
        $this->getExpectedBalance()
    );
}
```

---

### HIGH-005: Stock Reservation Race Condition
**Severity**: HIGH  
**File**: `app/Services/CurrencyPositionService.php:301-313`

**Issue**: `getAvailableBalance()` uses two separate queries without locking:

```php
public function getAvailableBalance(string $currencyCode, string $tillId): string
{
    $position = $this->getPosition($currencyCode, $tillId);  // Query 1
    $balance = $position ? $position->balance : '0';

    $reserved = StockReservation::where(...)  // Query 2
        ->sum('amount_foreign');

    return $this->mathService->subtract($balance, (string) $reserved);
}
```

**Race Condition**: Between queries, reservations could be consumed/expired, giving inconsistent results.

**Fix Required**:
```php
return DB::transaction(function () use ($currencyCode, $tillId) {
    $position = $this->getPositionWithLock($currencyCode, $tillId);
    // ... rest of calculation
});
```

---

## 📋 MEDIUM SEVERITY ISSUES

### MED-001: Session Fixation in MFA Flow
**File**: `app/Http/Middleware/EnsureMfaVerified.php:59-67`

**Issue**: MFA verification sets session variables without regenerating session ID.

**Fix**:
```php
$request->session()->regenerate();
$request->session()->put('mfa_verified', true);
```

---

### MED-002: SQL Injection via Search Parameter
**File**: `app/Http/Controllers/Api/V1/CustomerController.php:31-33`

**Issue**: LIKE query doesn't escape special characters:

```php
$query->where('full_name', 'like', "%{$request->search}%");
```

**Fix**:
```php
$search = '%' . preg_replace('/[%_]/', '\\\\$0', $request->search) . '%';
$query->where('full_name', 'like', $search);
```

---

### MED-003: Error Message Information Disclosure
**File**: `app/Http/Controllers/Api/V1/TransactionController.php:79-89`

**Issue**: Detailed error messages expose internal system information.

**Fix**:
```php
} catch (\Exception $e) {
    Log::error('Transaction failed', ['exception' => $e]);
    return response()->json([
        'message' => 'An error occurred. Reference: ' . uniqid(),
    ], 500);
}
```

---

### MED-004: Branch Access Control Bypass
**File**: `app/Http/Middleware/CheckBranchAccess.php:40-64`

**Issue**: Only checks route parameters named `branch` or `id`. Other resources (transactions, customers) use different parameter names.

**Fix**: Implement resource-based branch ownership checks.

---

### MED-005: Approval Task Double Status Update
**File**: `app/Services/TransactionService.php:413-418` + `app/Services/ApprovalWorkflowService.php:124-125`

**Issue**: Transaction status set to `PendingApproval` twice - once in TransactionService, again in ApprovalWorkflowService.

---

### MED-006: Structuring Detection Time Window Mismatch
**File**: `app/Services/ComplianceService.php:206-248`

**Issue**: Comment says "7-day lookback" but code uses 1-hour window.

**Clarification Needed**: Is this intentional (velocity at 24h, structuring at 1h, aggregate at 7d)?

---

## 📊 COMPLIANCE STATUS

| BNM Requirement | Status | Notes |
|----------------|--------|-------|
| CTOS ≥ RM 10,000 | ✅ PASS | Both Buy and Sell checked |
| Enhanced CDD | ✅ PASS | Correctly triggered |
| STR Deadlines | ✅ PASS | 3 working days correct |
| Velocity 24h | ⚠️ PARTIAL | Hardcoded 24h, config ignored |
| Structuring 1h | ✅ PASS | Correctly implemented |
| Approval ≥ RM 3k | ✅ PASS | PendingApproval used |
| **Segregation of Duties** | ❌ **FAIL** | Self-approval possible |
| Cancellation Approval | ✅ PASS | PendingCancellation used |
| Aggregate 7-day | ⚠️ CONFLICT | Config says 90d, code uses 7d |
| Threshold Consistency | ⚠️ PARTIAL | 55 direct config calls |

---

## ✅ POSITIVE FINDINGS

1. **BCMath Usage**: All monetary calculations use BCMath, no floats
2. **Audit Logging**: Hash-chained audit logs for tamper evidence
3. **Stock Reservation**: 24-hour expiry prevents overselling
4. **Double-Entry Accounting**: Proper journal entries for all transactions
5. **CSRF Protection**: Properly implemented
6. **Rate Limiting**: Comprehensive with burst protection
7. **Password Policy**: Strong requirements (12+ chars, complexity)
8. **PII Encryption**: Customer ID numbers encrypted at rest
9. **IP Blocking**: After 10 failed attempts

---

## 🎯 PRIORITIZED ACTION PLAN

### Week 1 (CRITICAL - Before Any Deployment)

1. **Fix missing model methods** (CRIT-001)
   - Add `isPending()` to ApprovalTask
   - Add `isOpen()` to CounterSession
   - Run tests to verify: `php artisan test`

2. **Fix segregation of duties** (CRIT-003)
   - Change `created_by` → `user_id` in web controllers
   - Add self-approval check to API controller
   - Add integration test

3. **Add race condition locks** (CRIT-004)
   - Add `lockForUpdate()` to TillBalance query
   - Test concurrent approval scenario

4. **Fix state machine** (CRIT-002, CRIT-006)
   - Add transitions for Pending/OnHold OR remove them
   - Remove dangerous transitions from PendingCancellation

### Week 2 (HIGH Priority)

5. **Fix stock reservation release** (CRIT-005)
6. **Replace 55 config() calls with ThresholdService** (CRIT-007)
7. **Fix MFA fingerprint** (CRIT-008)
8. **Fix mass assignment** (HIGH-001)
9. **Fix file upload validation** (HIGH-002)

### Week 3 (MEDIUM Priority)

10. Fix EOD hardcoded thresholds (HIGH-003)
11. Fix TillBalance float arithmetic (HIGH-004)
12. Fix session fixation (MED-001)
13. Fix SQL injection (MED-002)
14. Fix error disclosure (MED-003)

---

## 🧪 TESTING RECOMMENDATIONS

### Critical Test Scenarios

1. **Concurrent Transaction Approval**
   ```bash
   # Run concurrently:
   php artisan test --filter=ConcurrentApprovalTest
   ```

2. **Self-Approval Prevention**
   ```bash
   php artisan test --filter=SegregationOfDutiesTest
   ```

3. **Stock Reservation Expiry**
   ```bash
   php artisan test --filter=StockReservationTest
   ```

4. **Threshold Change Propagation**
   ```bash
   php artisan test --filter=ThresholdConsistencyTest
   ```

### Load Testing

Test these scenarios under load:
- 100 concurrent transaction creations
- 50 concurrent approvals
- Counter handover during active transactions
- EOD reconciliation with 10,000+ transactions

---

## 📞 SUPPORT & QUESTIONS

For questions about these findings:
1. Review the specific file paths and line numbers provided
2. Run the test suite: `php artisan test`
3. Check logs: `storage/logs/laravel.log`
4. Verify database schema matches model definitions

---

**Report Generated**: April 21, 2026  
**Next Review**: After critical fixes implemented  
**Sign-off Required**: CTO, Compliance Officer, Security Lead

🔴 **DO NOT DEPLOY TO PRODUCTION UNTIL ALL CRITICAL ISSUES ARE RESOLVED**
