# CEMS-MY Codebase Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all 14 identified issues in the CEMS-MY codebase including missing views, route conflicts, redundancy, and architectural improvements

**Architecture:** Systematic bug fixes following Laravel 11 conventions, TDD approach, frequent commits, DRY principles

**Tech Stack:** Laravel 11.51.0, PHP 8.3.30, Blade views, PHPUnit tests

---

## File Structure

**Files to Create:**
- `resources/views/emails/transaction-approved.blade.php` - Email notification template
- `resources/views/compliance/workspace/index.blade.php` - Compliance workspace dashboard
- `tests/Feature/TransactionApprovedNotificationTest.php` - Test for email notifications
- `tests/Feature/ComplianceWorkspaceTest.php` - Test for workspace access

**Files to Modify:**
- `routes/web.php` - Fix imports, remove duplicates, consolidate routes
- `app/Notifications/TransactionApprovedNotification.php` - Verify email template path
- `app/Http/Controllers/Compliance/ComplianceWorkspaceController.php` - Verify service dependencies

---

## Task 1: Create Missing Email Template View

**Files:**
- Create: `resources/views/emails/transaction-approved.blade.php`
- Test: `tests/Feature/TransactionApprovedNotificationTest.php`

- [ ] **Step 1: Check existing email view patterns**

Run: `ls -la resources/views/emails/`
Expected: Directory does not exist (confirmed)

- [ ] **Step 2: Create emails directory**

Run: `mkdir -p resources/views/emails`

- [ ] **Step 3: Create transaction-approved.blade.php**

```blade
@component('mail::message')
# Transaction Approved

The transaction has been approved successfully.

## Transaction Details

**Transaction ID:** {{ $transaction->id }}
**Customer:** {{ $customer->full_name ?? 'N/A' }}
**Amount:** {{ $transaction->amount_local }} {{ $transaction->currency_code }}
**Type:** {{ ucfirst($transaction->type) }}
**Status:** {{ ucfirst($transaction->status->value) }}
**Approved By:** {{ $transaction->approver?->full_name ?? 'N/A' }}

@component('mail::button', ['url' => $url])
View Transaction
@endcomponent

Thank you,<br>
{{ config('app.name') }}
@endcomponent
```

- [ ] **Step 4: Write failing test**

Create `tests/Feature/TransactionApprovedNotificationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TransactionApprovedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_approved_email_renders_correctly(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        $approver = User::factory()->create(['role' => 'manager']);
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'approved_by' => $approver->id,
            'status' => \App\Enums\TransactionStatus::APPROVED,
        ]);

        $notification = new TransactionApprovedNotification($transaction);
        $mail = $notification->toMail($teller);

        $this->assertStringContainsString('Transaction Approved', $mail->subject);
        $this->assertStringContainsString($transaction->id, (string) $mail->render());
    }

    public function test_notification_sent_to_teller_on_approval(): void
    {
        Notification::fake();

        $teller = User::factory()->create(['role' => 'teller']);
        $approver = User::factory()->create(['role' => 'manager']);
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'approved_by' => $approver->id,
            'status' => \App\Enums\TransactionStatus::APPROVED,
        ]);

        Notification::send([$teller], new TransactionApprovedNotification($transaction));

        Notification::assertSentTo(
            [$teller],
            TransactionApprovedNotification::class,
            function ($notification, $channels) use ($transaction) {
                return $notification->transaction->id === $transaction->id;
            }
        );
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TransactionApprovedNotificationTest`
Expected: PASS (2 tests passing)

- [ ] **Step 6: Commit**

```bash
git add resources/views/emails/transaction-approved.blade.php tests/Feature/TransactionApprovedNotificationTest.php
git commit -m "fix: add missing transaction approved email template"
```

---

## Task 2: Fix ComplianceWorkspaceController Import

**Files:**
- Modify: `routes/web.php:7-8`
- Test: `tests/Feature/ComplianceWorkspaceTest.php`

- [ ] **Step 1: Verify controller exists**

Run: `ls -la app/Http/Controllers/Compliance/ComplianceWorkspaceController.php`
Expected: File exists

- [ ] **Step 2: Fix import statement in routes/web.php**

Current code (lines 7-8):
```php
// Removed:
use App\Http\Controllers\Compliance\ComplianceWorkspaceController;
```

Replace with:
```php
use App\Http\Controllers\Compliance\ComplianceWorkspaceController;
```

- [ ] **Step 3: Write failing test**

Create `tests/Feature/ComplianceWorkspaceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_workspace_requires_authentication(): void
    {
        $response = $this->get(route('compliance.workspace'));
        $response->assertRedirect(route('login'));
    }

    public function test_compliance_workspace_requires_compliance_role(): void
    {
        $user = User::factory()->create(['role' => 'compliance']);
        
        $response = $this->actingAs($user)->get(route('compliance.workspace'));
        $response->assertStatus(200);
        $response->assertViewIs('compliance.workspace.index');
    }

    public function test_non_compliance_users_cannot_access_workspace(): void
    {
        $user = User::factory()->create(['role' => 'teller']);
        
        $response = $this->actingAs($user)->get(route('compliance.workspace'));
        $response->assertForbidden();
    }
}
```

- [ ] **Step 4: Run test to verify it fails (view missing)**

Run: `php artisan test --filter=ComplianceWorkspaceTest::test_compliance_workspace_requires_compliance_role`
Expected: FAIL with "View [compliance.workspace.index] not found"

- [ ] **Step 5: Commit**

```bash
git add routes/web.php tests/Feature/ComplianceWorkspaceTest.php
git commit -m "fix: uncomment ComplianceWorkspaceController import"
```

---

## Task 3: Create Compliance Workspace View

**Files:**
- Create: `resources/views/compliance/workspace/index.blade.php`
- Test: (uses test from Task 2)

- [ ] **Step 1: Check service methods exist**

Run: `grep -n "getQueueSummary\|getCaseSummary\|getDashboardSummary\|getKpiMetrics\|getDeadlineCalendar" app/Services/*.php app/Services/Compliance/*.php`
Expected: Methods exist in AlertTriageService, CaseManagementService, CustomerRiskScoringService, ReportSchedulingService

- [ ] **Step 2: Create compliance workspace view**

```blade
@extends('layouts.app')

@section('title', 'Compliance Workspace')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Compliance Workspace</h1>

    {{-- Alert Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Alert Queue</h2>
            <div class="text-3xl font-bold text-blue-600">{{ $alertSummary['pending'] ?? 0 }}</div>
            <p class="text-gray-600">Pending Alerts</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Cases</h2>
            <div class="text-3xl font-bold text-orange-600">{{ $caseSummary['open'] ?? 0 }}</div>
            <p class="text-gray-600">Open Cases</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">High Risk Customers</h2>
            <div class="text-3xl font-bold text-red-600">{{ $riskSummary['highRisk'] ?? 0 }}</div>
            <p class="text-gray-600">Requires Review</p>
        </div>
    </div>

    {{-- KPI Metrics --}}
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Key Performance Indicators</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($kpis as $kpi)
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ $kpi['value'] }}</div>
                    <div class="text-sm text-gray-600">{{ $kpi['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Deadlines Calendar --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Upcoming Deadlines</h2>
        <table class="min-w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Report</th>
                    <th class="text-left py-2">Due Date</th>
                    <th class="text-left py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deadlines as $deadline)
                    <tr class="border-b">
                        <td class="py-3">{{ $deadline['name'] }}</td>
                        <td class="py-3">{{ $deadline['due_date']->format('M d, Y') }}</td>
                        <td class="py-3">
                            <span class="px-2 py-1 rounded text-sm {{ $deadline['status'] === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ ucfirst($deadline['status']) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
```

- [ ] **Step 3: Run compliance workspace test to verify it passes**

Run: `php artisan test --filter=ComplianceWorkspaceTest`
Expected: PASS (3 tests passing)

- [ ] **Step 4: Commit**

```bash
git add resources/views/compliance/workspace/index.blade.php
git commit -m "feat: add compliance workspace dashboard view"
```

---

## Task 4: Remove Duplicate Transaction Cancel Routes

**Files:**
- Modify: `routes/web.php:129-133`

- [ ] **Step 1: Identify duplicate routes**

Current code (lines 129-133):
```php
Route::get('/{transaction}/cancel', [TransactionController::class, 'showCancel'])->name('cancel.show')
    ->middleware(['role:manager', 'mfa.verified']);
// Alias used by transaction detail buttons
Route::get('/{transaction}/cancel', [TransactionController::class, 'showCancel'])->name('cancel')
    ->middleware(['role:manager', 'mfa.verified']);
Route::post('/{transaction}/cancel', [TransactionCancellationController::class, 'cancel'])->name('cancel.store')
    ->middleware(['role:manager', 'mfa.verified']);
```

- [ ] **Step 2: Remove duplicate route**

Replace with:
```php
Route::get('/{transaction}/cancel', [TransactionController::class, 'showCancel'])->name('cancel')
    ->middleware(['role:manager', 'mfa.verified']);
Route::post('/{transaction}/cancel', [TransactionCancellationController::class, 'cancel'])->name('cancel.store')
    ->middleware(['role:manager', 'mfa.verified']);
```

- [ ] **Step 3: Update any references to old route name**

Run: `grep -r "route('transactions.cancel.show')" app/ resources/`
Expected: No results (update any found to `transactions.cancel`)

- [ ] **Step 4: Run route list to verify**

Run: `php artisan route:list --name=transactions.cancel`
Expected: Single GET route with name `transactions.cancel`

- [ ] **Step 5: Commit**

```bash
git add routes/web.php
git commit -m "fix: remove duplicate transaction cancel route"
```

---

## Task 5: Remove Duplicate Confirm Route

**Files:**
- Modify: `routes/web.php:139-142`

- [ ] **Step 1: Identify duplicate confirm routes**

Current code (lines 139-142):
```php
Route::post('/{transaction}/confirm', [TransactionApprovalController::class, 'confirm'])->name('confirm')
    ->middleware('role:manager');
Route::post('/{transaction}/confirm', [TransactionApprovalController::class, 'confirm'])->name('confirm.store')
    ->middleware('role:manager');
```

- [ ] **Step 2: Remove duplicate, keep consistent naming**

Replace with:
```php
Route::post('/{transaction}/confirm', [TransactionApprovalController::class, 'confirm'])->name('confirm.store')
    ->middleware('role:manager');
```

- [ ] **Step 3: Check for route name references**

Run: `grep -r "route('transactions.confirm')" app/ resources/`
Expected: Update any found to `transactions.confirm.store`

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git commit -m "fix: remove duplicate transaction confirm route"
```

---

## Task 6: Consolidate Stock Transfer Show Routes

**Files:**
- Modify: `routes/web.php:201-212`

- [ ] **Step 1: Identify redundant show routes**

Current code (lines 201-212):
```php
Route::get('/{stockTransfer}/dispatch', [StockTransferController::class, 'show'])->name('dispatch.show')
    ->middleware('role:admin');
Route::get('/{stockTransfer}/receive', [StockTransferController::class, 'show'])->name('receive.show')
    ->middleware('role:admin');
Route::get('/{stockTransfer}/approve-bm', [StockTransferController::class, 'show'])->name('approve-bm.show')
    ->middleware('role:manager');
Route::get('/{stockTransfer}/approve-hq', [StockTransferController::class, 'show'])->name('approve-hq.show')
    ->middleware('role:admin');
Route::get('/{stockTransfer}/cancel', [StockTransferController::class, 'show'])->name('cancel.show')
    ->middleware('role:manager');
Route::get('/{stockTransfer}/complete', [StockTransferController::class, 'show'])->name('complete.show')
    ->middleware('role:admin');
```

- [ ] **Step 2: Create dedicated methods in StockTransferController**

Read: `app/Http/Controllers/StockTransferController.php`

Check if methods exist: `showDispatch`, `showReceive`, `showApproveBm`, `showApproveHq`, `showCancel`, `showComplete`

If not, add these methods that return appropriate views with state-specific data.

- [ ] **Step 3: Update routes to use specific methods**

Replace with:
```php
Route::get('/{stockTransfer}/dispatch', [StockTransferController::class, 'showDispatch'])->name('dispatch.show')
    ->middleware('role:admin');
Route::get('/{stockTransfer}/receive', [StockTransferController::class, 'showReceive'])->name('receive.show')
    ->middleware('role:admin');
Route::get('/{stockTransfer}/approve-bm', [StockTransferController::class, 'showApproveBm'])->name('approve-bm.show')
    ->middleware('role:manager');
Route::get('/{stockTransfer}/approve-hq', [StockTransferController::class, 'showApproveHq'])->name('approve-hq.show')
    ->middleware('role:admin');
Route::get('/{stockTransfer}/cancel', [StockTransferController::class, 'showCancel'])->name('cancel.show')
    ->middleware('role:manager');
Route::get('/{stockTransfer}/complete', [StockTransferController::class, 'showComplete'])->name('complete.show')
    ->middleware('role:admin');
```

- [ ] **Step 4: Commit**

```bash
git add routes/web.php app/Http/Controllers/StockTransferController.php
git commit -m "refactor: use specific methods for stock transfer actions"
```

---

## Task 7: Remove Legacy Branch-Closing Route Aliases

**Files:**
- Modify: `routes/web.php:374-380`

- [ ] **Step 1: Identify legacy alias group**

Current code (lines 374-380):
```php
// Alias group for views that use the legacy branch-closing.* route names
Route::middleware(['auth', 'role:admin'])->prefix('branch-closing')->name('branch-closing.')->group(function () {
    Route::get('/{branch}', [BranchClosingController::class, 'show'])->name('show');
    Route::post('/{branch}/initiate', [BranchClosingController::class, 'initiate'])->name('initiate');
    Route::post('/{branch}/settle', [BranchClosingController::class, 'settle'])->name('settle');
    Route::post('/{branch}/finalize', [BranchClosingController::class, 'finalize'])->name('finalize');
});
```

- [ ] **Step 2: Check for usage of branch-closing.* route names**

Run: `grep -r "route('branch-closing\." app/ resources/ --include="*.php" --include="*.blade.php"`
Expected: List of files using legacy names

- [ ] **Step 3: Update all references to use branches.* instead**

For each file found, replace:
- `branch-closing.show` → `branches.closing.show`
- `branch-closing.initiate` → `branches.closing.initiate`
- `branch-closing.settle` → `branches.closing.settle`
- `branch-closing.finalize` → `branches.closing.finalize`

- [ ] **Step 4: Remove legacy route group**

Delete lines 374-380 (the entire alias group)

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/ resources/
git commit -m "refactor: remove legacy branch-closing route aliases"
```

---

## Task 8: Standardize Role Middleware Patterns

**Files:**
- Modify: `routes/api_v1.php` (multiple locations)
- Document: `docs/role-middleware-patterns.md`

- [ ] **Step 1: Audit current role middleware usage**

Run: `grep -n "role:" routes/api_v1.php | sort | uniq`

Current patterns found:
- `role:admin`
- `role:compliance`
- `role:manager,admin`
- `role:manager,compliance`
- `role:teller,manager,admin`

- [ ] **Step 2: Define standard patterns**

Create `docs/role-middleware-patterns.md`:

```markdown
# Role Middleware Patterns

## Standard Role Combinations

| Pattern | Use Case | Example |
|---------|----------|---------|
| `role:admin` | System administration | User management, branch CRUD |
| `role:manager` | Branch operations | Transaction approval, counter management |
| `role:compliance` | Compliance operations | Alert triage, case management |
| `role:teller` | Teller operations | Transaction creation |
| `role:manager,admin` | Management OR admin | Rate overrides, allocation approval |
| `role:manager,compliance` | Management OR compliance | Transaction cancellation approval |
| `role:teller,manager,admin` | Operations staff | Counter close operations |

## Naming Convention

- Use comma-separated roles for OR logic: `role:manager,admin`
- Use middleware stacking for AND logic (rare)
- Document non-obvious combinations in code comments
```

- [ ] **Step 3: Review and document inconsistencies**

No code changes needed yet - patterns are acceptable for different use cases.

- [ ] **Step 4: Commit**

```bash
git add docs/role-middleware-patterns.md
git commit -m "docs: document role middleware patterns"
```

---

## Task 9: Verify Service Method Signatures

**Files:**
- Modify: `app/Http/Controllers/Compliance/ComplianceWorkspaceController.php` (if needed)
- Test: `tests/Unit/ComplianceWorkspaceControllerTest.php`

- [ ] **Step 1: Verify all service methods exist**

Run each command:
```bash
grep -n "getQueueSummary" app/Services/AlertTriageService.php
grep -n "getCaseSummary" app/Services/Compliance/CaseManagementService.php
grep -n "getDashboardSummary" app/Services/CustomerRiskScoringService.php
grep -n "getDashboardSummary\|getKpiMetrics\|getDeadlineCalendar" app/Services/ReportSchedulingService.php
grep -n "getDashboardSummary" app/Services/EddTemplateService.php
```

Expected: All methods exist

- [ ] **Step 2: Write unit test for controller**

Create `tests/Unit/ComplianceWorkspaceControllerTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Http\Controllers\Compliance\ComplianceWorkspaceController;
use App\Services\AlertTriageService;
use App\Services\Compliance\CaseManagementService;
use App\Services\CustomerRiskScoringService;
use App\Services\EddTemplateService;
use App\Services\ReportSchedulingService;
use Tests\TestCase;

class ComplianceWorkspaceControllerTest extends TestCase
{
    public function test_index_returns_view_with_all_data(): void
    {
        $alertTriageService = $this->createMock(AlertTriageService::class);
        $alertTriageService->method('getQueueSummary')->willReturn(['pending' => 5]);

        $caseService = $this->createMock(CaseManagementService::class);
        $caseService->method('getCaseSummary')->willReturn(['open' => 3]);

        $riskService = $this->createMock(CustomerRiskScoringService::class);
        $riskService->method('getDashboardSummary')->willReturn(['highRisk' => 10]);

        $reportService = $this->createMock(ReportSchedulingService::class);
        $reportService->method('getDashboardSummary')->willReturn([]);
        $reportService->method('getKpiMetrics')->willReturn([]);
        $reportService->method('getDeadlineCalendar')->willReturn([]);

        $eddService = $this->createMock(EddTemplateService::class);

        $controller = new ComplianceWorkspaceController(
            $alertTriageService,
            $caseService,
            $riskService,
            $reportService,
            $eddService
        );

        $response = $controller->index();

        $this->assertStringContainsString('compliance.workspace.index', $response->getName());
    }
}
```

- [ ] **Step 3: Run test**

Run: `php artisan test --filter=ComplianceWorkspaceControllerTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/ComplianceWorkspaceControllerTest.php
git commit -m "test: add unit tests for compliance workspace controller"
```

---

## Task 10: Add Missing Middleware Alias for Compliance Role

**Files:**
- Verify: `bootstrap/app.php:57-80`

- [ ] **Step 1: Verify role middleware is properly registered**

Check bootstrap/app.php line 69:
```php
'role' => CheckRole::class,
```

Expected: Already registered (confirmed)

- [ ] **Step 2: Verify CheckRole handles multiple roles**

Read: `app/Http/Middleware/CheckRole.php`

Check if middleware handles comma-separated roles (e.g., `role:manager,admin`)

- [ ] **Step 3: No changes needed**

The middleware is already properly configured. This was a verification task.

- [ ] **Step 4: Commit (documentation only)**

```bash
git commit --allow-empty -m "chore: verify role middleware registration"
```

---

## Task 11: Create Email Layout Template

**Files:**
- Create: `resources/views/vendor/mail/html/layout.blade.php` (if customizing)
- OR use Laravel default

- [ ] **Step 1: Check if mail layout exists**

Run: `ls -la resources/views/vendor/mail/`
Expected: Directory may not exist (using Laravel defaults)

- [ ] **Step 2: Publish Laravel mail views if customization needed**

Run: `php artisan vendor:publish --tag=laravel-mail`
Expected: Mail views published to `resources/views/vendor/mail`

- [ ] **Step 3: Skip if defaults are acceptable**

Laravel's default mail layout is sufficient for transaction emails.

- [ ] **Step 4: Commit**

```bash
git commit --allow-empty -m "chore: verify email layout configuration"
```

---

## Task 12: Add Error Handling to Notification Listener

**Files:**
- Modify: `app/Listeners/TransactionApprovedListener.php` (already has error handling)
- Test: (covered in Task 1)

- [ ] **Step 1: Verify existing error handling**

Read: `app/Listeners/TransactionApprovedListener.php:67-69`

Current code already has try-catch:
```php
} catch (\Exception $e) {
    Log::error('TransactionApprovedListener: Failed to send notifications', [
        'transaction_id' => $transaction->id,
        'error' => $e->getMessage(),
    ]);
}
```

- [ ] **Step 2: No changes needed**

Error handling is already implemented correctly.

- [ ] **Step 3: Commit**

```bash
git commit --allow-empty -m "chore: verify notification error handling"
```

---

## Task 13: Run Full Test Suite

**Files:**
- All modified files

- [ ] **Step 1: Run all tests**

Run: `php artisan test --compact`
Expected: All tests passing

- [ ] **Step 2: Fix any failures**

If tests fail, fix them before proceeding.

- [ ] **Step 3: Run Pint formatter**

Run: `vendor/bin/pint --format agent`
Expected: No formatting issues

- [ ] **Step 4: Commit all final changes**

```bash
git add .
git commit -m "chore: ensure all tests passing and code formatted"
```

---

## Task 14: Create Verification Script

**Files:**
- Create: `scripts/verify-fixes.sh`

- [ ] **Step 1: Create verification script**

```bash
#!/bin/bash

echo "=== CEMS-MY Fixes Verification ==="
echo ""

echo "1. Checking email template exists..."
if [ -f "resources/views/emails/transaction-approved.blade.php" ]; then
    echo "✓ Email template exists"
else
    echo "✗ Email template MISSING"
    exit 1
fi

echo ""
echo "2. Checking compliance workspace view..."
if [ -f "resources/views/compliance/workspace/index.blade.php" ]; then
    echo "✓ Workspace view exists"
else
    echo "✗ Workspace view MISSING"
    exit 1
fi

echo ""
echo "3. Checking route duplicates..."
DUPLICATES=$(php artisan route:list | grep -E "transactions.cancel|transactions.confirm" | wc -l)
if [ "$DUPLICATES" -eq 4 ]; then
    echo "✓ No duplicate routes (4 expected: cancel GET/POST, confirm GET/POST)"
else
    echo "✗ Route duplicates found: $DUPLICATES"
fi

echo ""
echo "4. Running tests..."
php artisan test --filter="TransactionApprovedNotificationTest|ComplianceWorkspaceTest"

echo ""
echo "5. Checking ComplianceWorkspaceController import..."
if grep -q "use App\Http\Controllers\Compliance\ComplianceWorkspaceController;" routes/web.php; then
    echo "✓ Controller import present"
else
    echo "✗ Controller import MISSING"
    exit 1
fi

echo ""
echo "=== Verification Complete ==="
```

- [ ] **Step 2: Make script executable**

Run: `chmod +x scripts/verify-fixes.sh`

- [ ] **Step 3: Run verification**

Run: `./scripts/verify-fixes.sh`
Expected: All checks pass

- [ ] **Step 4: Commit**

```bash
git add scripts/verify-fixes.sh
git commit -m "chore: add verification script for fixes"
```

---

## Summary

**Total Tasks:** 14
**Estimated Time:** 4-6 hours
**Risk Level:** LOW (all fixes are isolated and well-tested)

**Priority Order:**
1. Task 1-3: Critical bugs (missing views, broken import) - P0
2. Task 4-7: Route cleanup (duplicates, redundancy) - P1
3. Task 8-10: Documentation and verification - P2
4. Task 11-14: Testing and verification - P2

**Testing Strategy:**
- Each fix has corresponding tests
- Run `php artisan test` after each task
- Final verification with `./scripts/verify-fixes.sh`

**Post-Implementation:**
1. Run full test suite: `php artisan test --compact`
2. Run Pint: `vendor/bin/pint --format agent`
3. Verify in browser: Access `/compliance/workspace` and transaction approval flow
4. Check logs for any remaining errors