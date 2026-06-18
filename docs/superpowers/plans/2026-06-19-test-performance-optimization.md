# Test Suite Performance Optimization Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduce full test suite execution time from 330s (5.5 min) to under 120s (2 min) through targeted optimizations.

**Architecture:** Three-pronged approach: (1) Remove unnecessary DB operations from Unit tests, (2) Switch Feature tests from RefreshDatabase to DatabaseTransactions, (3) Add PHPUnit parallel execution. Each phase is independently deployable and measurable.

**Tech Stack:** PHPUnit 11.5.55, Laravel 11, SQLite in-memory, paratest for parallel execution.

## Global Constraints

- PHP 8.3.30, Laravel 11, PHPUnit 11.5.55
- SQLite in-memory database for tests (`:memory:`)
- All tests must continue passing after each change
- No changes to production code
- Each task includes a measurement step to verify improvement

## Current State

| Metric | Value |
|--------|-------|
| Total tests | 1074 |
| Total time | 330s (5.5 min) |
| Unit tests | 582 (191s) |
| Feature tests | 492 (165s) |
| Unit tests with RefreshDatabase | 76 |
| Feature tests with RefreshDatabase | 51 |
| setUp methods | 81 |
| Factory calls | 680+ |

## Phase 1: Remove Unnecessary DB from Unit Tests (Target: -60s)

### Task 1: Audit Unit Tests for DB Usage

**Files:**
- Read: All 76 Unit test files that use RefreshDatabase

- [ ] **Step 1: Identify tests that don't actually query DB**

Run: `rg "use RefreshDatabase" tests/Unit --no-heading -l`

For each file, check if tests actually query the database or just instantiate services.

- [ ] **Step 2: Create categorization list**

Document which tests:
- A: Don't use DB at all (remove RefreshDatabase)
- B: Use DB for setup but not in assertions (use DatabaseTransactions)
- C: Genuinely need RefreshDatabase (keep)

- [ ] **Step 3: Verify categorization**

Run a sample from each category to confirm classification is correct.

### Task 2: Remove RefreshDatabase from Pure Unit Tests (Category A)

**Files:**
- Modify: ~50 Unit test files (those that don't query DB)

- [ ] **Step 1: Remove RefreshDatabase from Category A tests**

For each file in Category A, remove:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
// and
use RefreshDatabase;
```

- [ ] **Step 2: Run unit test suite**

Run: `php artisan test --compact --testsuite=Unit`
Expected: All 582 tests pass

- [ ] **Step 3: Measure improvement**

Run: `php artisan test --compact --testsuite=Unit 2>&1 | grep Duration`
Expected: Significant reduction from 191s

### Task 3: Switch Category B Tests to DatabaseTransactions

**Files:**
- Modify: ~10 Unit test files that need DB for setup

- [ ] **Step 1: Replace RefreshDatabase with DatabaseTransactions**

For each file in Category B, change:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
// to
use Illuminate\Foundation\Testing\DatabaseTransactions;
// and
use RefreshDatabase;
// to
use DatabaseTransactions;
```

- [ ] **Step 2: Run unit test suite**

Run: `php artisan test --compact --testsuite=Unit`
Expected: All 582 tests pass

- [ ] **Step 3: Measure improvement**

Run: `php artisan test --compact --testsuite=Unit 2>&1 | grep Duration`
Expected: Further reduction

## Phase 2: Optimize Feature Tests (Target: -40s)

### Task 4: Switch Feature Tests to DatabaseTransactions

**Files:**
- Modify: 51 Feature test files with RefreshDatabase

- [ ] **Step 1: Replace RefreshDatabase with DatabaseTransactions**

For each Feature test file, change:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;
// to
use Illuminate\Foundation\Testing\DatabaseTransactions;
// and
use RefreshDatabase;
// to
use DatabaseTransactions;
```

- [ ] **Step 2: Run feature test suite**

Run: `php artisan test --compact --testsuite=Feature`
Expected: All 492 tests pass

- [ ] **Step 3: Measure improvement**

Run: `php artisan test --compact --testsuite=Feature 2>&1 | grep Duration`
Expected: Significant reduction from 165s

### Task 5: Add Service Fakes Where Appropriate

**Files:**
- Modify: Feature tests that interact with queues, mail, notifications

- [ ] **Step 1: Identify tests that trigger side effects**

Run: `rg "Event::dispatch|Queue::push|Mail::to|Notification::send" tests/Feature --no-heading`

- [ ] **Step 2: Add fakes to relevant tests**

For tests that trigger these but don't assert on them:
```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

// In setUp():
Event::fake();
Queue::fake();
Mail::fake();
Notification::fake();
```

- [ ] **Step 3: Run feature test suite**

Run: `php artisan test --compact --testsuite=Feature`
Expected: All tests pass, potentially faster

### Task 6: Optimize Heavy setUp Methods

**Files:**
- Modify: Feature tests with complex setUp

- [ ] **Step 1: Identify slow setUp methods**

Run: `rg "function setUp" tests/Feature --no-heading -l`

Look for setUp methods that create many models.

- [ ] **Step 2: Refactor to lazy creation**

Move model creation from setUp to individual test methods, or use factory states for common patterns.

- [ ] **Step 3: Run feature test suite**

Run: `php artisan test --compact --testsuite=Feature`
Expected: All tests pass

## Phase 3: Parallel Execution (Target: -50%)

### Task 7: Install and Configure Paratest

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Install paratest**

Run: `composer require --dev brianium/paratest`

- [ ] **Step 2: Configure phpunit.xml**

Add parallel configuration:
```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
>
```

- [ ] **Step 3: Verify paratest works**

Run: `vendor/bin/paratest --processes=4`
Expected: Tests pass, faster than sequential

- [ ] **Step 4: Measure improvement**

Run: `time vendor/bin/paratest --processes=4`
Expected: Under 120s

### Task 8: Optimize Parallel Execution

**Files:**
- Modify: `phpunit.xml`, test configuration

- [ ] **Step 1: Tune process count**

Test with different process counts (2, 4, 8) to find optimal.

- [ ] **Step 2: Add --no-configuration flag if needed**

If tests interfere with each other, use `--no-configuration` and run separately.

- [ ] **Step 3: Final measurement**

Run: `time vendor/bin/paratest --processes=4`
Expected: Under 120s

## Phase 4: Additional Optimizations

### Task 9: Reduce Factory Calls

**Files:**
- Modify: Feature tests with excessive factory usage

- [ ] **Step 1: Identify tests with many factory calls**

Run: `rg "create\(\)" tests/Feature --no-heading -l | head -10`

- [ ] **Step 2: Refactor to use shared state**

Group related tests into test classes that share setup, or use `actingAs` with cached users.

- [ ] **Step 3: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests pass, faster

### Task 10: Add Test Groups for Selective Running

**Files:**
- Modify: `phpunit.xml`, test files

- [ ] **Step 1: Add group annotations**

Add `@group slow` to tests taking >1s.

- [ ] **Step 2: Configure default group exclusion**

In `phpunit.xml`:
```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
    <testsuite name="Fast">
        <directory>tests/Unit</directory>
        <directory>tests/Feature</directory>
        <exclude>tests/Feature/Slow</exclude>
    </testsuite>
</testsuites>
```

- [ ] **Step 3: Document usage**

Add to README:
```bash
# Run all tests
php artisan test

# Run fast tests only
php artisan test --testsuite=Fast

# Run with parallel execution
vendor/bin/paratest --processes=4
```

## Success Metrics

| Metric | Before | Target | Actual |
|--------|--------|--------|--------|
| Total time | 330s | <120s | |
| Unit time | 191s | <60s | |
| Feature time | 165s | <60s | |
| Parallel speedup | 1x | 2-3x | |

## Commit Strategy

Each phase should be committed separately:
1. Phase 1: `fix(tests): remove unnecessary DB operations from unit tests`
2. Phase 2: `fix(tests): optimize feature test execution`
3. Phase 3: `feat(tests): add parallel test execution`
4. Phase 4: `chore(tests): add test groups and optimize factories`

## Risk Mitigation

- Run full test suite after each change
- Verify no test regressions
- Keep RefreshDatabase for tests that genuinely need it
- Test parallel execution thoroughly to catch race conditions
