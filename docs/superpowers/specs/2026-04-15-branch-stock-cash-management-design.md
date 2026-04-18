# Branch Stock & Cash Management Design Spec

**Date:** 2026-04-15
**Version:** 1.0
**Status:** Draft

---

## 1. Overview

### 1.1 Purpose

Implement a branch-level stock and cash management system where:
- Each branch (including HQ) maintains independent cash/stock pools
- Teller has personal stock allocation assigned to their counter
- Teller can request stock from branch pool at opening
- Manager can modify teller's allocation anytime
- EOD: Full return of unused allocation to branch pool
- HQ→Branch transfers via inter-branch stock transfer + journal voucher

### 1.2 Scope

- Counter/Teller personal stock allocation
- Branch pool management
- Daily opening workflow (teller requests, manager approves)
- Intraday transaction processing
- EOD closing workflow
- Handover workflow
- View permissions by role

---

## 2. Data Model

### 2.1 New Models

#### BranchPool
Tracks branch-level cash/stock availability per currency.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| branch_id | bigint | FK to branches |
| currency_code | string | Currency code (MYR, USD, etc.) |
| available_balance | decimal | Balance available for allocation |
| allocated_balance | decimal | Amount currently allocated to tellers |
| total_balance | decimal | Total in pool (available + allocated) |
| updated_at | timestamp | Last update |

**Unique constraint:** (branch_id, currency_code)

#### TellerAllocation
Tracks teller's personal stock allocation per currency per day.

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | FK to users (teller) |
| branch_id | bigint | FK to branches |
| counter_id | bigint | FK to counters (assigned counter) |
| currency_code | string | Currency code |
| allocated_amount | decimal | Original allocated amount |
| current_balance | decimal | Remaining balance |
| status | enum | active, returned, closed |
| session_date | date | Date of allocation |
| opened_at | datetime | When allocation opened |
| closed_at | datetime | When returned |
| requested_amount | decimal | Amount teller requested (may differ from approved) |
| approved_by | bigint | FK to users (manager who approved) |
| approved_at | datetime | When approved |
| created_at | timestamp | Record creation |
| updated_at | timestamp | Last update |

**Indexes:**
- (user_id, currency_code, session_date, status)
- (counter_id, session_date)

#### StockTransfer (existing, enhanced)
Use existing StockTransfer for HQ→Branch transfers.

Add new transfer type: `inter_branch_replenishment`

### 2.2 Modified Models

#### TillBalance
Add FK to TellerAllocation.

| Field | Type | Description |
|-------|------|-------------|
| teller_allocation_id | bigint | FK to teller_allocations (nullable) |

#### CounterSession
Add fields for allocation tracking.

| Field | Type | Description |
|-------|------|-------------|
| teller_allocation_id | bigint | FK to teller_allocations (nullable) |
| daily_limit_myr | decimal | Daily transaction limit for this session |
| requested_amount_myr | decimal | Amount teller requested |

#### Counter
Update to link permanently to teller by default.

| Field | Type | Description |
|-------|------|-------------|
| assigned_teller_id | bigint | FK to users (permanent teller, nullable) |

---

## 3. Role-Based Access Control

### 3.1 Role Hierarchy

```
Admin (Principal Officer)
  └── HQ Manager (manages HQ branch only)
        └── Teller (assigned to specific counter)
  └── Branch Manager (manages assigned branch)
        └── Teller (assigned to specific counter)
  └── Compliance Officer (separate track)
```

### 3.2 Permission Matrix

| Permission | Teller | Branch Manager | HQ Manager | Admin |
|------------|--------|----------------|------------|-------|
| View own allocation | ✅ | ✅ | ✅ | ✅ |
| View own transactions | ✅ | ✅ | ✅ | ✅ |
| View branch tellers' allocations | ❌ | ✅ | ✅ | ✅ |
| View branch pool balance | ❌ | ✅ | ✅ | ✅ |
| View branch transactions | ❌ | ✅ | ✅ | ✅ |
| View all branches consolidated | ❌ | ❌ | ❌ | ✅ |
| Request opening amount | ✅ | ❌ | ❌ | ❌ |
| Approve/modify teller allocation | ❌ | ✅ (own branch) | ✅ (HQ) | ✅ |
| Modify allocation anytime | ❌ | ✅ | ✅ | ✅ |
| Open/close branch | ❌ | ✅ (own branch) | ✅ (HQ) | ✅ |
| View accounting (own branch) | ❌ | ✅ | ✅ | ✅ |
| View accounting (all) | ❌ | ❌ | ❌ | ✅ |
| Create inter-branch transfer | ❌ | ❌ | ✅ (to own branch) | ✅ |
| Approve inter-branch transfer | ❌ | ❌ | ✅ | ✅ |
| Force-close teller session | ❌ | ✅ | ✅ | ✅ |

### 3.3 Branch Access

- **Teller**: Own branch only
- **Branch Manager**: Own branch only
- **HQ Manager**: HQ branch only
- **Admin**: All branches

---

## 4. Daily Workflow

### 4.1 Branch Opening Sequence

```
[1. HQ Manager / Branch Manager]
    │
    └── Set branch pool balance
        (via Stock Transfer from HQ or previous day return)
        └── Journal Voucher: Cash In
    │
[2. Manager opens branch for day]
    │
    └── Branch status → "open"
    │
[3. Teller requests opening]
    │
    └── Enter desired amount (MYR equivalent per currency)
    └── System validates: requested ≤ available pool
    └── If amount differs from default → status: "pending_approval"
    └── If same as default → status: "approved"
    │
[4. Manager approves/modifies]
    │
    └── Can modify requested_amount
    └── Sets daily_limit_myr
    └── Approval recorded with approved_by, approved_at
    │
[5. Teller opens counter]
    │
    └── System creates TellerAllocation
    └── System deducts from BranchPool.available
    └── System adds to BranchPool.allocated
    └── CounterSession → teller_allocation_id
    └── Status: active
```

### 4.2 Intraday Operations

#### Teller performs transaction
```
Teller initiates transaction (Buy/Sell)
    │
    └── System validates:
        ├── Teller has active allocation
        ├── Transaction amount ≤ current_balance (for Buy)
        └── Daily total + this ≤ daily_limit_myr
    │
    └── If Sell: currency comes FROM branch pool
    └── If Buy: currency goes TO branch pool
    │
    └── Transaction recorded with teller_allocation_id
    └── TellerAllocation.current_balance updated
```

#### Manager modifies allocation
```
Manager selects teller
    │
    └── Can ADD or REDUCE allocation
    └── System validates: new_amount ≤ available pool
    │
    └── If ADD:
        ├── BranchPool.available -= added
        ├── BranchPool.allocated += added
        └── TellerAllocation.current_balance += added
    │
    └── If REDUCE:
        ├── Validate: reduce ≤ (allocated - current_balance)
        ├── If teller has enough unused: return to pool
        └── Else: reduce from future allocations
    │
    └── Audit log recorded
```

#### Handover (intraday)
```
Teller A → Teller B handover
    │
    └── Manager approves
    │
    └── TellerAllocation transferred:
        ├── from_user_id → to_user_id
        └── current_balance preserved
    │
    └── CounterSession transferred:
        ├── user_id → new teller
        └── status: handed_over
    │
    └── Variance calculated & recorded
```

### 4.3 End of Day Close

```
[Teller initiates close]
    │
    └── Teller enters closing counts
    │
    └── System calculates:
        ├── Expected = opening + net transactions
        └── Variance = actual - expected
    │
    └── If variance > threshold:
        ├── Manager investigation required
        └── Cannot return to pool until approved
    │
    └── If variance OK or approved:
        ├── Full return to BranchPool
        ├── BranchPool.available += current_balance
        ├── BranchPool.allocated -= allocated_amount
        └── TellerAllocation.status → "returned"
    │
    └── CounterSession.status → "closed"
    │
    └── TellerAllocation.status → "closed"
    │
    └── TillBalance updated
```

### 4.4 EOD / Period Auto-Settlement

```
[Midnight or period close trigger]
    │
    └── For each open TellerAllocation:
        ├── Force close session
        ├── Return balance to BranchPool
        └── Mark status: "auto_returned"
    │
    └── For each BranchPool:
        ├── allocated_balance = 0
        └── available_balance = total_balance
    │
    └── Journal Entry generated for period:
        Debit: Branch Pool Assets
        Credit: Period Settlement
```

---

## 5. Business Rules

### 5.1 Opening Rules
1. Teller can only request opening when assigned to a counter
2. Requested amount must be ≤ branch pool available
3. If teller requests different amount, manager approval required
4. Daily limit (MYR) set by manager during approval
5. Counter assignment is permanent unless reassigned by admin

### 5.2 Transaction Rules
1. Buy (teller sells foreign currency): deducted from teller allocation
2. Sell (teller buys foreign currency): added to teller allocation
3. Transaction cannot exceed teller's current_balance (for Buy)
4. Daily cumulative cannot exceed daily_limit_myr
5. Refund can go to teller allocation OR branch pool (user selects)

### 5.3 Allocation Rules
1. One allocation per currency per teller per day
2. Allocation is currency-specific
3. Manager can modify allocation anytime (add/reduce)
4. Full return of unused balance at EOD
5. Auto-return if teller absent at EOD

### 5.4 Variance Rules
1. Yellow threshold (RM 100): requires notes
2. Red threshold (RM 500): requires manager approval
3. Any variance recorded in handover record

### 5.5 Pool Rules
1. Branch pool independent per branch (including HQ)
2. HQ is a branch - has its own pool
3. Inter-branch transfer for moving stock between branches
4. Stock transfer first, then Journal Voucher for accounting

---

## 6. Views & Reporting

### 6.1 Teller View
- My daily allocation (per currency)
- My transactions (today)
- My session history

### 6.2 Manager View (own branch)
- All teller allocations (active)
- Branch pool balance
- Branch transactions
- Variance reports
- EOD summary

### 6.3 Admin View
- All branches consolidated
- Branch pool balances (all)
- Inter-branch transfers
- Company-wide EOD report
- Accounting (all branches)

---

## 7. API Endpoints

### 7.1 Branch Pool
- `GET /api/v1/branch/{branch}/pool` - View pool balance
- `POST /api/v1/branch/{branch}/pool/replenish` - Add to pool (Journal Voucher)

### 7.2 Teller Allocation
- `POST /api/v1/teller/{teller}/allocation/request` - Request opening amount
- `GET /api/v1/teller/{teller}/allocation` - View current allocation
- `PATCH /api/v1/manager/teller/{teller}/allocation` - Approve/modify allocation
- `POST /api/v1/teller/{teller}/allocation/return` - EOD return to pool

### 7.3 Counter Session
- `POST /api/v1/counter/{counter}/open` - Open with allocation
- `POST /api/v1/counter/{counter}/close` - Close and return
- `POST /api/v1/counter/{counter}/handover` - Intraday handover

### 7.4 Reporting
- `GET /api/v1/branch/{branch}/allocation-summary` - Branch allocation report
- `GET /api/v1/branch/{branch}/eod-report` - EOD report

---

## 8. Implementation Phases

### Phase 1: Data Model & Migrations
- Create BranchPool model & migration
- Create TellerAllocation model & migration
- Add fields to TillBalance, CounterSession, Counter
- Create BranchPoolService, TellerAllocationService

### Phase 2: Opening Workflow
- Manager sets branch pool
- Teller requests opening
- Manager approves/modifies
- Counter opens with allocation

### Phase 3: Transaction Integration
- Modify transaction flow to use allocation
- Validate against allocation balance
- Track daily limits

### Phase 4: EOD & Handover
- EOD close with full return
- Handover workflow
- Variance handling

### Phase 5: Reporting & Permissions
- Role-based view restrictions
- Branch/company consolidated views
- EOD reports

---

## 9. Open Questions (Answered)

| # | Question | Answer |
|---|----------|--------|
| 1 | Counter assignment | Permanent unless need arises |
| 2 | Refunds | Both teller allocation OR branch pool (user selects) |
| 3 | Cross-branch customer | Manual key for first-time business |
| 4 | Absent teller | Session stays open; manager can force-close |
| 5 | Multi-currency | Separate allocation per currency |
| 6 | Emergency close | Session stays open |
| 7 | Period end | Auto-settlement |
| 8 | Daily limit | Variable, set by manager during approval |
| 9 | Manager adjustment | Yes, anytime |
| 10 | Unused allocation | Full return at EOD |
| 11 | HQ replenishment | Stock transfer first, then Journal Voucher |
| 12 | HQ is branch | Yes, all branches independent |
| 13 | Inter-branch | Use inter-branch transfer if needed |
