# Test Suite Outstanding Issues — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the 1 failing test, eliminate 9 PHPUnit 12 deprecation warnings, and migrate all `@group` docblock annotations to PHP attributes.

**Architecture:** Two changes: (1) Add `DomainException` handling in the global exception handler so `InsufficientStockException` returns 422 instead of 500. (2) Replace `@group slow` docblock annotations with `#[Group('slow')]` PHP attributes across 9 test files.

**Tech Stack:** PHP 8.3, Laravel 10, PHPUnit 11

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 11
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run affected tests after each task to verify no regressions
- Use `php artisan make:test --phpunit` for new tests

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `app/Exceptions/Domain/DomainException.php` | Modify | Add `getStatusCode()` method returning 422 |
| `app/Exceptions/Handler.php` | Modify | Add `DomainException` check before generic 500 fallback |
| `tests/Feature/CriticalTransactionWorkflowTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |
| `tests/Feature/RouteVerificationTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |
| `tests/Feature/SecurityTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |
| `tests/Feature/TransactionAccountingVerificationTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |
| `tests/Feature/TransactionCancellationFlowTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |
| `tests/Feature/TransactionTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |
| `tests/Feature/TransactionWorkflowTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |
| `tests/Unit/Services/SanctionsOrchestrationServiceTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |
| `tests/Unit/TransactionServiceTest.php` | Modify | Replace `@group slow` docblock with `#[Group('slow')]` attribute |

---

### Task 1: Add `getStatusCode()` to DomainException

**Files:**
- Modify: `app/Exceptions/Domain/DomainException.php:7-18`

**Interfaces:**
- Consumes: (none)
- Produces: `DomainException::getStatusCode(): int` — returns 422 for all domain exceptions

- [ ] **Step 1: Add `getStatusCode()` method to DomainException**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    public function getStatusCode(): int
    {
        return 422;
    }

    public function getSeverity(): string
    {
        return 'warning';
    }

    public function getErrorCode(): string
    {
        return class_basename(static::class);
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Exceptions/Domain/DomainException.php
git commit -m "fix: add getStatusCode() to DomainException returning 422"
```

---

### Task 2: Handle DomainException in Exception Handler

**Files:**
- Modify: `app/Exceptions/Handler.php:53-69`

**Interfaces:**
- Consumes: `DomainException::getStatusCode(): int` (from Task 1)
- Produces: All `DomainException` subclasses now return 422 instead of 500 for API requests

- [ ] **Step 1: Update Handler::render() to check for DomainException**

Replace the status code logic in the `render()` method. The current code at line 54:

```php
$status = $this->isHttpException($e) ? $e->getStatusCode() : 500;
```

Should become:

```php
$status = match (true) {
    $this->isHttpException($e) => $e->getStatusCode(),
    $e instanceof \App\Exceptions\Domain\DomainException => $e->getStatusCode(),
    default => 500,
};
```

Also add the import at the top of the file:

```php
use App\Exceptions\Domain\DomainException;
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Run the failing test to verify it passes**

```bash
php artisan test --compact --group=slow --filter="concurrent_transactions_respect_stock_reservations"
```

Expected: PASS (422 returned for InsufficientStockException)

- [ ] **Step 4: Commit**

```bash
git add app/Exceptions/Handler.php
git commit -m "fix: handle DomainException in Handler to return 422 instead of 500"
```

---

### Task 3: Migrate `@group slow` to `#[Group('slow')]` Attributes (9 files)

**Files:**
- Modify: all 9 files listed in the table above

**Interfaces:**
- Consumes: (none)
- Produces: All `@group slow` docblock annotations replaced with `#[Group('slow')]` PHP attributes, eliminating 9 PHPUnit 12 deprecation warnings

- [ ] **Step 1: Update CriticalTransactionWorkflowTest.php**

Open `tests/Feature/CriticalTransactionWorkflowTest.php`. The current annotation block at lines 34-36:

```php
/**
 * @group slow
 */
class CriticalTransactionWorkflowTest extends TestCase
```

Replace with:

```php
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for critical transaction workflows
 *
 * These tests verify:
 * - Segregation of duties (self-approval prevention)
 * - Stock reservation and release
 * - Transaction state machine transitions
 * - Concurrent transaction handling
 * - Threshold consistency
 */
#[Group('slow')]
class CriticalTransactionWorkflowTest extends TestCase
```

Note: The duplicate docblock above `@group slow` (lines 24-33) is the actual class documentation and should be preserved. Only remove the `@group slow` docblock (lines 34-36) and replace with the attribute.

- [ ] **Step 2: Update RouteVerificationTest.php**

Open `tests/Feature/RouteVerificationTest.php`. Replace `@group slow` docblock with:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class RouteVerificationTest extends TestCase
```

- [ ] **Step 3: Update SecurityTest.php**

Open `tests/Feature/SecurityTest.php`. Replace `@group slow` docblock with:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class SecurityTest extends TestCase
```

- [ ] **Step 4: Update TransactionAccountingVerificationTest.php**

Open `tests/Feature/TransactionAccountingVerificationTest.php`. Replace `@group slow` docblock with:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class TransactionAccountingVerificationTest extends TestCase
```

- [ ] **Step 5: Update TransactionCancellationFlowTest.php**

Open `tests/Feature/TransactionCancellationFlowTest.php`. Replace `@group slow` docblock with:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class TransactionCancellationFlowTest extends TestCase
```

- [ ] **Step 6: Update TransactionTest.php**

Open `tests/Feature/TransactionTest.php`. Replace `@group slow` docblock with:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class TransactionTest extends TestCase
```

- [ ] **Step 7: Update TransactionWorkflowTest.php**

Open `tests/Feature/TransactionWorkflowTest.php`. Replace `@group slow` docblock with:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class TransactionWorkflowTest extends TestCase
```

- [ ] **Step 8: Update SanctionsOrchestrationServiceTest.php**

Open `tests/Unit/Services/SanctionsOrchestrationServiceTest.php`. Replace `@group slow` docblock with:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class SanctionsOrchestrationServiceTest extends TestCase
```

- [ ] **Step 9: Update TransactionServiceTest.php**

Open `tests/Unit/TransactionServiceTest.php`. Replace `@group slow` docblock with:

```php
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class TransactionServiceTest extends TestCase
```

- [ ] **Step 10: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 11: Run full test suite to verify no regressions and no deprecation warnings**

```bash
php artisan test --compact 2>&1
```

Expected: 978 passed, 0 warnings about `@group` metadata

- [ ] **Step 12: Run slow tests to verify group still works**

```bash
php artisan test --compact --group=slow 2>&1
```

Expected: All slow tests run, 0 deprecation warnings

- [ ] **Step 13: Commit**

```bash
git add tests/Feature/CriticalTransactionWorkflowTest.php tests/Feature/RouteVerificationTest.php tests/Feature/SecurityTest.php tests/Feature/TransactionAccountingVerificationTest.php tests/Feature/TransactionCancellationFlowTest.php tests/Feature/TransactionTest.php tests/Feature/TransactionWorkflowTest.php tests/Unit/Services/SanctionsOrchestrationServiceTest.php tests/Unit/TransactionServiceTest.php
git commit -m "fix(tests): migrate @group slow to #[Group('slow')] PHP attributes for PHPUnit 12 compat"
```

---

### Task 4: Final Verification

- [ ] **Step 1: Run full default test suite**

```bash
php artisan test --compact
```

Expected: 978 passed, 0 warnings, ~35s

- [ ] **Step 2: Run slow test suite**

```bash
php artisan test --compact --group=slow
```

Expected: 96 passed, 0 failures, 0 deprecation warnings

- [ ] **Step 3: Verify no remaining `@group` docblock annotations**

```bash
grep -rn "@group" tests/ --include="*.php" | grep -v "#\[Group"
```

Expected: No output (all migrated)

- [ ] **Step 4: Push to GitHub**

```bash
git push origin main
```
