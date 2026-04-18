# Phase 2: Compliance Findings UI

## Overview
Add web UI for viewing and managing automated compliance findings.

## Pages Required

### 1. Findings List (`/compliance/findings`)
- **Route:** `GET /compliance/findings`
- **Controller:** `FindingController` (new, web controller)
- **View:** `compliance/findings/index.blade.php`

**Features:**
- Summary stats cards (by severity, by status)
- Filter bar: Status, Severity, Type, Date Range
- Table: Severity badge, Type, Subject, Details preview, Status, Date
- Actions: View Details

### 2. Finding Detail (`/compliance/findings/{id}`)
- **Route:** `GET /compliance/findings/{id}`
- **Controller:** `FindingController::show()`
- **View:** `compliance/findings/show.blade.php`

**Features:**
- Finding details card with all info
- Subject information (linked customer/transaction)
- Status badge
- Actions: Dismiss (with reason), Create Case

## Data from API

**GET `/api/v1/compliance/findings` returns:**
- Array of findings with type, severity, status, subject info

**GET `/api/v1/compliance/findings/{id}` returns:**
- Single finding with full details and relationships

**POST `/api/v1/compliance/findings/{id}/dismiss`**
- Body: `{ reason: string }`

## Finding Types
- VelocityExceeded
- StructuringPattern
- AggregateTransaction
- StrDeadline
- SanctionMatch
- LocationAnomaly
- CurrencyFlowAnomaly
- CounterfeitAlert
- RiskScoreChange

## Finding Statuses
- New
- Reviewed
- Dismissed
- CaseCreated

## Sidebar

Add under Compliance section:
```
<a href="/compliance/findings" class="nav-item">
    <svg><!-- alert icon --></svg>
    <span>Findings</span>
</a>
```

## Acceptance Criteria

1. View all findings with correct severity badges
2. Filter findings by status, severity, type, date
3. View finding details
4. Dismiss finding with reason
5. Create case from finding
