# Counter Operations Fix - Implementation Spec

**Date:** 2026-05-05
**Status:** Approved

---

## 1. Overview

Fix broken counter operations in CEMS-MY, specifically:
1. Handover workflow redesign (two-phase)
2. Add missing close API endpoint
3. Fix two unrelated bugs (`getCounterStatus` param mismatch, `calculateExpectedBalance` missing)

---

## 2. Handover Workflow Redesign

### Current Broken Flow

```
initiateHandover() → sets session to HandedOver → acknowledgeHandover() expects PendingHandover → FAIL
```

### New Two-Phase Flow

```
initiateHandover() → sets session to PendingHandover, creates handover record
    → (manager reviews)
    → acknowledgeHandover() → sets session to HandedOver, finalizes new session
```

### Changes

#### CounterService::initiateHandover() (line ~468-495)

- Change `status` from `CounterSessionStatus::HandedOver` to `CounterSessionStatus::PendingHandover`
- Add comment explaining two-phase workflow

#### CounterHandoverService::acknowledgeHandover() (line ~25-51)

- Validate session status is `PendingHandover` (already exists)
- Set session status to `HandedOver`
- Update handover record with `acknowledged_at`
- No longer need to set session to `Open` - that's handled by the new session creation

---

## 3. Add Close API Endpoint

### New Route

```
POST /api/v1/counters/{counterId}/close
```

### Handler

`CounterApiController::close()` - new method

### Request Body

```json
{
  "closing_floats": {
    "USD": "5000.00",
    "EUR": "3000.00"
  },
  "notes": "End of shift"
}
```

### Service Call

`CounterService::closeSession()` - already exists

### Response

```json
{
  "success": true,
  "session": { ... },
  "variance": { ... }
}
```

### Files

| File | Change |
|------|--------|
| `routes/api_v1.php` | Add `POST /counters/{counter}/close` |
| `app/Http/Controllers/Api/V1/CounterApiController.php` | Add `close()` method |

---

## 4. Bug Fixes

### Bug 1: getCounterStatus Parameter Mismatch

**File:** `app/Http/Controllers/CounterController.php:183`

**Current:**
```php
$status = $this->counterService->getCounterStatus($counter, $today);
```

**Fix:** Remove `$today` argument
```php
$status = $this->counterService->getCounterStatus($counter);
```

### Bug 2: calculateExpectedBalance Does Not Exist

**File:** `app/Services/EmergencyCounterService.php:138-149`

**Current:**
```php
$expected = (new CounterService(...))->calculateExpectedBalance(...);
```

**Fix:** Use `TillBalance::getExpectedBalance()` instead
```php
$expected = $balance->getExpectedBalance();
```

---

## 5. Test Updates

### Files to Update

| Test File | Changes |
|-----------|---------|
| `tests/Feature/CounterHandoverAcknowledgeTest.php` | Update `test_handover_requires_pending_status` - should now test that initiating handover sets PendingHandover, not HandedOver |

### Test Workflow Changes

1. `test_acknowledge_handover_updates_session_status` - verify status transitions to HandedOver
2. `test_handover_requires_pending_status` - verify acknowledgment fails if not PendingHandover
3. `test_handover_creates_new_session_for_receiver` - verify new session lifecycle

---

## 6. Files Summary

| File | Action |
|------|--------|
| `app/Services/CounterService.php` | Fix `initiateHandover` status |
| `app/Services/CounterHandoverService.php` | Update acknowledge workflow |
| `app/Services/EmergencyCounterService.php` | Fix `getExpectedBalance` usage |
| `app/Http/Controllers/CounterController.php` | Fix `getCounterStatus` call |
| `app/Http/Controllers/Api/V1/CounterApiController.php` | Add `close()` method |
| `routes/api_v1.php` | Add close endpoint |
| `tests/Feature/CounterHandoverAcknowledgeTest.php` | Update tests |

---

## 7. Acceptance Criteria

- [ ] `POST /api/v1/counters/{id}/close` endpoint works
- [ ] Handover workflow: initiate sets PendingHandover, acknowledge sets HandedOver
- [ ] `getCounterStatus` called with correct parameter count
- [ ] `EmergencyCounterService::getVariance` uses existing `getExpectedBalance()` method
- [ ] All updated tests pass

---

## 8. Out of Scope

- CounterStateMachine (architectural improvement, separate task)
- Counter opening workflow changes
- Web UI changes to counter operations
