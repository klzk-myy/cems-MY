# Counter Operations Fix - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix broken counter operations - handover workflow, missing close API, and two bugs.

**Architecture:** Changes to CounterService (handover status), CounterHandoverService (acknowledge logic), EmergencyCounterService (balance calc), CounterController (param fix), and new API endpoint for close session.

**Tech Stack:** Laravel 10, PHPUnit, Sanctum

---

## File Summary

| File | Action |
|------|--------|
| `app/Services/CounterService.php` | Fix `initiateHandover` status from `HandedOver` to `PendingHandover` |
| `app/Services/CounterHandoverService.php` | Update `acknowledgeHandover` to set `HandedOver` (not `Open`) |
| `app/Services/EmergencyCounterService.php` | Replace `calculateExpectedBalance` with `TillBalance::getExpectedBalance()` |
| `app/Http/Controllers/CounterController.php` | Remove extra `$today` param from `getCounterStatus` call |
| `app/Http/Controllers/Api/V1/CounterApiController.php` | Create new controller with `close()` method |
| `routes/api_v1.php` | Add `POST /counters/{counterId}/close` route |
| `tests/Feature/CounterHandoverAcknowledgeTest.php` | Update test assertions for new `HandedOver` status |

---

## Task 1: Fix CounterService::initiateHandover Status

**Files:**
- Modify: `app/Services/CounterService.php:468-473`

- [ ] **Step 1: Read the current code to verify line numbers**

Run: `sed -n '465,475p' app/Services/CounterService.php`

Expected output shows `CounterSessionStatus::HandedOver` at line 470

- [ ] **Step 2: Change HandedOver to PendingHandover**

```php
// Line 470
'status' => CounterSessionStatus::HandedOver,
// Change to:
'status' => CounterSessionStatus::PendingHandover,
```

- [ ] **Step 3: Run handover tests to see current behavior**

Run: `php artisan test --compact --filter="CounterHandoverAcknowledgeTest" 2>&1 | tail -20`

Expected: Some tests may fail after change (will fix in Task 3)

- [ ] **Step 4: Commit**

```bash
git add app/Services/CounterService.php
git commit -m "fix(counter): initiateHandover sets PendingHandover not HandedOver"
```

---

## Task 2: Fix EmergencyCounterService::getVariance

**Files:**
- Modify: `app/Services/EmergencyCounterService.php:136-149`

- [ ] **Step 1: Read current broken code**

Run: `sed -n '136,149p' app/Services/EmergencyCounterService.php`

Expected: Shows inline CounterService instantiation calling `calculateExpectedBalance`

- [ ] **Step 2: Replace inline CounterService with TillBalance::getExpectedBalance()**

Replace lines 136-149:
```php
// OLD (broken):
$expected = (new CounterService(
    new TellerAllocationService(
        app(BranchPoolService::class),
        app(MathService::class)
    ),
    app(ThresholdService::class)
))->calculateExpectedBalance(
    $balance->currency_code,
    $counter->id,
    $session->session_date->toDateString(),
    $balance->opening_balance
);

// NEW (fixed):
$expected = $balance->getExpectedBalance();
```

- [ ] **Step 3: Run tests to verify**

Run: `php artisan test --compact --filter="EmergencyCounter" 2>&1 | tail -10`

Expected: PASS (no tests for EmergencyCounterService directly, but we can verify syntax)

- [ ] **Step 4: Commit**

```bash
git add app/Services/EmergencyCounterService.php
git commit -m "fix(counter): use TillBalance::getExpectedBalance instead of missing method"
```

---

## Task 3: Fix CounterHandoverService::acknowledgeHandover

**Files:**
- Modify: `app/Services/CounterHandoverService.php:45-51`

- [ ] **Step 1: Read current code**

Run: `cat app/Services/CounterHandoverService.php`

Expected: Line 46 sets `CounterSessionStatus::Open`

- [ ] **Step 2: Change status from Open to HandedOver**

```php
// Line 45-49 - change status from Open to HandedOver
$handover->counterSession->update([
    'status' => CounterSessionStatus::HandedOver,  // Was: Open
    'physical_count_verified' => $verified,
    'handover_notes' => $notes,
]);
```

- [ ] **Step 3: Run tests - expect failure**

Run: `php artisan test --compact --filter="test_acknowledge_handover_updates_session_status" 2>&1`

Expected: FAIL - test at line 125 expects `Open` but we now set `HandedOver`

- [ ] **Step 4: Update test expectations**

Modify `tests/Feature/CounterHandoverAcknowledgeTest.php:125`:
```php
// Line 125 - change from Open to HandedOver
$this->assertEquals(CounterSessionStatus::HandedOver, $result['session']->status);
// Was: CounterSessionStatus::Open
```

- [ ] **Step 5: Run tests to verify fix**

Run: `php artisan test --compact --filter="CounterHandoverAcknowledgeTest" 2>&1 | tail -15`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/CounterHandoverService.php tests/Feature/CounterHandoverAcknowledgeTest.php
git commit -m "fix(counter): acknowledgeHandover sets HandedOver, update test"
```

---

## Task 4: Fix CounterController::status Parameter Mismatch

**Files:**
- Modify: `app/Http/Controllers/CounterController.php:183`

- [ ] **Step 1: Read current code**

Run: `sed -n '180,189p' app/Http/Controllers/CounterController.php`

Expected: Line 183 has `getCounterStatus($counter, $today)`

- [ ] **Step 2: Remove extra $today parameter**

```php
// Line 183 - remove $today argument
$status = $this->counterService->getCounterStatus($counter, $today);
// Change to:
$status = $this->counterService->getCounterStatus($counter);
```

- [ ] **Step 3: Verify syntax**

Run: `php -l app/Http/Controllers/CounterController.php`

Expected: No syntax errors

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/CounterController.php
git commit -m "fix(counter): remove extra param from getCounterStatus call"
```

---

## Task 5: Add Close API Endpoint

**Files:**
- Create: `app/Http/Controllers/Api/V1/CounterApiController.php`
- Modify: `routes/api_v1.php:316-320`

- [ ] **Step 1: Read CounterService::closeSession signature**

Run: `grep -n "public function closeSession" app/Services/CounterService.php`

Expected: Line ~99

- [ ] **Step 2: Create CounterApiController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CounterApiController extends Controller
{
    public function __construct(
        private CounterService $counterService
    ) {}

    public function close(Request $request, string $counterId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'closing_floats' => 'required|array',
            'closing_floats.*' => 'numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $counter = \App\Models\Counter::findOrFail($counterId);

        $session = $counter->sessions()
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No open session found for this counter',
            ], 404);
        }

        try {
            $result = $this->counterService->closeSession(
                $session,
                $request->user(),
                $request->input('closing_floats'),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Counter closed successfully',
                'session' => $result['session'] ?? $session->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close counter: '.$e->getMessage(),
            ], 500);
        }
    }
}
```

- [ ] **Step 3: Add route to api_v1.php after line 319**

```php
// After line 319 (after handover acknowledge route)
// Add close endpoint
Route::post('/{counterId}/close', [CounterApiController::class, 'close'])
    ->middleware(['role:teller,manager,admin', 'mfa.verified'])
    ->name('api.v1.counters.close');
```

- [ ] **Step 4: Run tests - verify no errors**

Run: `php artisan test --compact --filter="Counter" 2>&1 | tail -10`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/CounterApiController.php routes/api_v1.php
git commit -m "feat(api): add POST /counters/{id}/close endpoint"
```

---

## Task 6: Run Full Test Suite

- [ ] **Step 1: Run all counter-related tests**

Run: `php artisan test --compact --filter="Counter" 2>&1 | tail -30`

Expected: All PASS

- [ ] **Step 2: Run full test suite**

Run: `php artisan test --compact 2>&1 | tail -20`

Expected: All tests pass, no regressions

---

## Spec Coverage Checklist

- [x] Handover workflow: initiateHandover sets PendingHandover (Task 1)
- [x] Handover workflow: acknowledgeHandover sets HandedOver (Task 3)
- [x] Add Close API endpoint (Task 5)
- [x] getCounterStatus param fix (Task 4)
- [x] EmergencyCounterService getExpectedBalance fix (Task 2)
- [x] Test updates for new HandedOver status (Task 3)

---

## Dependencies

Task 3 must complete before Task 6 (full test run).
