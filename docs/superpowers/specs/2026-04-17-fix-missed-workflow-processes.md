# Fix Missed Transaction & Accounting Workflow Processes

**Date:** 2026-04-17
**Status:** Approved
**Version:** 1.0

---

## 1. Overview

Fix critical gaps in the transaction and accounting workflow where background jobs and event listeners are missing or not properly scheduled.

---

## 2. Issues to Fix

### 2.1 Critical: Missing Scheduled Jobs

| Job | File | Purpose | Schedule |
|-----|------|---------|----------|
| `VelocityMonitorJob` | `app/Jobs/Compliance/VelocityMonitorJob.php` | AML velocity detection | Daily 04:30 |
| `StructuringMonitorJob` | `app/Jobs/Compliance/StructuringMonitorJob.php` | Structuring pattern detection | Daily 04:45 |
| `StrDeadlineMonitorJob` | `app/Jobs/Compliance/StrDeadlineMonitorJob.php` | STR submission deadline tracking | Daily 05:00 |
| `ReconcileDeferredAccountingJob` | `app/Jobs/Accounting/ReconcileDeferredAccountingJob.php` | Auto-reconcile deferred journal entries | Daily 21:00 |

### 2.2 Medium: Missing Event Listeners

| Event | Problem | Fix |
|-------|---------|-----|
| `TransactionCancelled` | Never dispatched | Add dispatch in `TransactionCancellationService` |
| `TransactionApproved` | Has no listeners | Create `TransactionApprovedListener` |
| `StrDraftGenerated` | Never dispatched | Add dispatch in `StrReportService` |

---

## 3. Implementation

### 3.1 Add to Console/Kernel.php

```php
// Daily compliance monitors
$schedule->job(new VelocityMonitorJob)
    ->dailyAt('04:30')
    ->appendOutputTo(storage_path('logs/monitor-velocity.log'));

$schedule->job(new StructuringMonitorJob)
    ->dailyAt('04:45')
    ->appendOutputTo(storage_path('logs/monitor-structuring.log'));

$schedule->job(new StrDeadlineMonitorJob)
    ->dailyAt('05:00')
    ->appendOutputTo(storage_path('logs/monitor-str-deadline.log'));

// EOD reconciliation for deferred accounting
$schedule->job(new ReconcileDeferredAccountingJob)
    ->dailyAt('21:00')
    ->appendOutputTo(storage_path('logs/reconcile-deferred-accounting.log'));
```

### 3.2 Add TransactionCancelled Dispatch

In `TransactionCancellationService::approveCancellation()`, add:
```php
Event::dispatch(new TransactionCancelled($transaction, $reason, $approver->id));
```

### 3.3 Create TransactionApprovedListener

```php
class TransactionApprovedListener
{
    public function handle(TransactionApproved $event): void
    {
        $transaction = $event->transaction;

        // Notify teller/manager
        NotificationService::notifyApprovalComplete($transaction);

        // Audit log
        AuditService::logTransaction('transaction_approved', $transaction);
    }
}
```

### 3.4 Add StrDraftGenerated Dispatch

In `StrReportService::createDraft()`, add:
```php
Event::dispatch(new StrDraftGenerated($report));
```

---

## 4. Files Changed

| Action | File |
|--------|------|
| Modify | `app/Console/Kernel.php` |
| Modify | `app/Services/TransactionCancellationService.php` |
| Create | `app/Listeners/TransactionApprovedListener.php` |
| Modify | `app/Services/StrReportService.php` |
| Modify | `app/Providers/EventServiceProvider.php` |

---

## 5. Acceptance Criteria

- [ ] VelocityMonitorJob scheduled daily at 04:30
- [ ] StructuringMonitorJob scheduled daily at 04:45
- [ ] StrDeadlineMonitorJob scheduled daily at 05:00
- [ ] ReconcileDeferredAccountingJob scheduled daily at 21:00
- [ ] TransactionCancelled event fires on cancellation approval
- [ ] TransactionApprovedListener sends notifications
- [ ] StrDraftGenerated event fires on manual STR creation
- [ ] All tests pass
