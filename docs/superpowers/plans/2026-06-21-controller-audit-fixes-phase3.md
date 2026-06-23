# Controller Audit Fixes — Phase 3 (High Priority)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix unbounded queries, PHP loops doing DB work, duplicate count queries, and null dereference risks.

**Architecture:** 4 tasks targeting high-priority performance and correctness issues.

**Tech Stack:** Laravel 10, PHP 8.3, PHPUnit 10

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 10
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run affected tests after each task
- Preserve exact API response shapes

---

### Task 1: Fix unbounded queries in TransactionController and CounterController

**Files:**
- Modify: `app/Http/Controllers/TransactionController.php`
- Modify: `app/Http/Controllers/CounterController.php`

**Context:** TransactionController::create() loads ALL customers and branches with Customer::all() and Branch::all(). CounterController loads all active users and currencies in multiple methods.

- [ ] **Step 1: Read TransactionController.php and replace unbounded queries**

Replace `Customer::all()` with `Customer::select('id', 'full_name')->orderBy('full_name')->get()` (only needed fields).
Replace `Branch::all()` with `Branch::select('id', 'name')->orderBy('name')->get()`.

- [ ] **Step 2: Read CounterController.php and replace unbounded queries**

In `history()`, `showHandover()`, and other methods:
- Replace `User::where('is_active', true)->get()` with `User::select('id', 'username', 'role')->where('is_active', true)->get()`
- Replace `Currency::where('is_active', true)->get()` with `Currency::select('code', 'name')->where('is_active', true)->get()`
- Extract the repeated Currency query to a private method or cache it

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter="Transaction|Counter"
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/TransactionController.php app/Http/Controllers/CounterController.php
git commit -m "fix: optimize unbounded queries in TransactionController and CounterController"
```

---

### Task 2: Fix PHP loops doing DB work in StockCashController

**Files:**
- Modify: `app/Http/Controllers/StockCashController.php`

**Context:** StockCashController::index() uses PHP foreach loops to sum variance and cash-in-hand instead of DB aggregates.

- [ ] **Step 1: Read StockCashController.php**

- [ ] **Step 2: Replace PHP foreach sum with DB aggregate**

Replace:
```php
$variance = 0;
foreach ($todayBalances as $balance) {
    $variance += $balance->variance;
}
```

With:
```php
$variance = $todayBalances->sum('variance');
```

And replace the cash-in-hand foreach with:
```php
$cashInHand = $myrBalances->sum(fn ($b) => $b->closing_balance ?? $b->opening_balance);
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=StockCash
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/StockCashController.php
git commit -m "fix: replace PHP foreach sums with collection aggregates in StockCashController"
```

---

### Task 3: Batch DashboardController count queries

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

**Context:** DashboardController::compliance() runs 4 separate FlaggedTransaction count queries. Should be one grouped query.

- [ ] **Step 1: Read DashboardController.php compliance() method**

- [ ] **Step 2: Replace 4 count queries with single grouped query**

Replace:
```php
$pending = FlaggedTransaction::where('status', 'Pending')->count();
$reviewing = FlaggedTransaction::where('status', 'Under_Review')->count();
$resolved = FlaggedTransaction::where('status', 'Resolved')->count();
$dismissed = FlaggedTransaction::where('status', 'Dismissed')->count();
```

With:
```php
$counts = FlaggedTransaction::selectRaw('status, COUNT(*) as count')
    ->groupBy('status')
    ->pluck('count', 'status');
$pending = $counts->get('Pending', 0);
$reviewing = $counts->get('Under_Review', 0);
$resolved = $counts->get('Resolved', 0);
$dismissed = $counts->get('Dismissed', 0);
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=Dashboard
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/DashboardController.php
git commit -m "fix: batch 4 compliance count queries into 1 grouped query in DashboardController"
```

---

### Task 4: Fix null dereference in CounterOpeningController

**Files:**
- Modify: `app/Http/Controllers/Api/V1/CounterOpeningController.php`

**Context:** Line 128 calls `$teller->branch_id` after `User::find()` which can return null.

- [ ] **Step 1: Read CounterOpeningController.php approveAndOpen() method**

- [ ] **Step 2: Change User::find() to User::findOrFail()**

Replace `User::find($validated['teller_id'])` with `User::findOrFail($validated['teller_id'])`.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=CounterOpening
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/CounterOpeningController.php
git commit -m "fix: use findOrFail in CounterOpeningController to prevent null dereference"
```
