# Phase 1: CTOS Reports UI

## Overview
Add web UI for viewing and submitting CTOS reports to BNM.

## Pages Required

### 1. CTOS List View (`/compliance/ctos`)
- **Route:** `GET /compliance/ctos`
- **Controller:** `CtosController` (new)
- **View:** `compliance/ctos/index.blade.php`

**Features:**
- Summary cards: Total, Draft, Submitted, Acknowledged, Rejected
- Filter bar: Status dropdown, Branch dropdown, Date range
- Table: CTOS Number, Customer, Amount, Status, Date, Actions
- Actions: View, Submit (if Draft)

### 2. CTOS Detail View (`/compliance/ctos/{id}`)
- **Route:** `GET /compliance/ctos/{id}`
- **Controller:** `CtosController::show()`
- **View:** `compliance/ctos/show.blade.php`

**Features:**
- Customer info card
- Transaction details
- Report status with badge
- Submit button (if Draft) with MFA verification
- Timeline of status changes

## Data Model

**CtosReport fields:**
- `ctos_number` (CTOS-YYYYMM-XXXXX)
- `customer_id`, `customer_name`
- `id_type`, `id_number_masked`
- `date_of_birth`, `nationality`
- `amount_local`, `amount_foreign`, `currency_code`
- `transaction_type` (Buy/Sell)
- `transaction_id`
- `status` (Draft/Submitted/Acknowledged/Rejected)
- `submitted_at`, `submitted_by`, `bnm_reference`
- `created_by`, `created_at`

## API Endpoints to Use

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/v1/ctos` | List with filters |
| GET | `/api/v1/ctos/{id}` | Get single |
| POST | `/api/v1/ctos/{id}/submit` | Submit to BNM |

## Sidebar

Add under Compliance section:
```
<a href="/compliance/ctos" class="nav-item">
    <svg><!-- document icon --></svg>
    <span>CTOS Reports</span>
</a>
```

## Acceptance Criteria

1. List page shows all CTOS reports with correct status badges
2. Filters work (status, branch, date range)
3. Detail page shows full report information
4. Submit button triggers MFA verification then submits
5. Status updates after submission
6. 360+ tests still pass
