# User Manual Workflow Diagrams Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 8 system workflow diagrams using mermaid syntax to the user manual, one under each corresponding section.

**Architecture:** Each workflow diagram will be inserted as a markdown mermaid code block directly after the heading for each section, before the existing content. All diagrams use standard mermaid flowchart syntax.

**Tech Stack:** Mermaid v10, Laravel Blade, HTML

---

## Task 1: Login & MFA Workflow Diagram

**Files:**
- Modify: `resources/views/user-manual/index.blade.php:71-72`

- [ ] **Step 1: Insert diagram after section heading**

Add this immediately after line 72:
```mermaid
flowchart TD
    A[User visits login page] --> B[Enter username & password]
    B --> C{Credentials valid?}
    C -->|No| D[Show error message]
    D --> B
    C -->|Yes| E{MFA already setup?}
    E -->|No| F[Show MFA setup QR code]
    F --> G[Scan with authenticator app]
    G --> H[Enter verification code]
    H --> I{Code valid?}
    I -->|No| J[Retry code entry]
    J --> H
    I -->|Yes| K[Force password change if temp password]
    K --> L[Complete setup, redirect to dashboard]
    E -->|Yes| M[Enter MFA code]
    M --> N{Code valid?}
    N -->|No| O[Show invalid code error]
    O --> M
    N -->|Yes| P[Check for pending actions]
    P --> L
```

- [ ] **Step 2: Run lint check**
Run: `./vendor/bin/pint resources/views/user-manual/index.blade.php`
Expected: No errors, file formatted correctly

---

## Task 2: Counter Opening Workflow Diagram

**Files:**
- Modify: `resources/views/user-manual/index.blade.php:137-138`

- [ ] **Step 1: Insert diagram after section heading**

Add this immediately after line 138:
```mermaid
flowchart TD
    A[Select assigned counter] --> B[Click Open Counter]
    B --> C[Physical count opening float]
    C --> D[Enter denominations for ALL currencies]
    D --> E[Verify counts match physical cash]
    E --> F{Variance within threshold?}
    F -->|< RM5| G[Confirm opening]
    F -->|> RM5| H[Notify manager]
    H --> I[Manager reviews and approves]
    I --> G
    G --> J[Counter status set to OPEN]
    J --> K[Audit log entry created]
    K --> L[Ready for transactions]
```

---

## Task 3: Counter Closing Workflow Diagram

**Files:**
- Modify: `resources/views/user-manual/index.blade.php:171-172`

- [ ] **Step 1: Insert diagram after section heading**

```mermaid
flowchart TD
    A[Complete all pending transactions] --> B[Count physical cash in drawer]
    B --> C[Enter actual closing amounts]
    C --> D[System calculates variance]
    D --> E{Variance amount?}
    E -->|< RM1| F[Add note, submit closing]
    E -->|RM1-RM5| G[Manager review required]
    G --> H[Manager approves closing]
    H --> F
    E -->|> RM5| I[Investigate variance]
    I --> J[Resolve discrepancy]
    J --> C
    F --> K[Print closing slip]
    K --> L[Counter status set to CLOSED]
    L --> M[EOD reconciliation initiated]
```

---

## Task 4: Transaction Processing Workflow Diagram

**Files:**
- Modify: `resources/views/user-manual/index.blade.php:240-241`

- [ ] **Step 1: Insert diagram after section heading**

```mermaid
flowchart TD
    A[Start New Transaction] --> B[Select Buy / Sell type]
    B --> C[Select currency]
    C --> D[Enter amount]
    D --> E[System calculates rate & totals]
    E --> F[Enter customer details]
    F --> G{KYC Required?}
    G -->|Yes| H[Verify identification]
    H --> I
    G -->|No| I[Verify amounts with customer]
    I --> J[Count cash received]
    J --> K{Amount over 50k?}
    K -->|Yes| L[Request manager approval]
    L --> M[Manager authorizes]
    M --> N
    K -->|No| N[Process transaction]
    N --> O[Update counter positions]
    O --> P[Print receipt]
    P --> Q[Hand cash & receipt to customer]
```

---

## Task 5: Customer Creation & KYC Workflow Diagram

**Files:**
- Modify: `resources/views/user-manual/index.blade.php:296-297`

- [ ] **Step 1: Insert diagram after section heading**

```mermaid
flowchart TD
    A[Enter customer ID number] --> B{Existing customer?}
    B -->|Yes| C[Load customer profile]
    C --> D
    B -->|No| D[Enter customer details]
    D --> E{Transaction amount?}
    E -->|< RM3,000| F[Proceed without ID]
    E -->|RM3k-RM10k| G[Enter ID number only]
    E -->|RM10k-RM50k| H[Full name, address, contact]
    E -->|>= RM50k| I[Full EDD verification]
    I --> J[Source of funds confirmation]
    J --> K[Manager approval]
    K --> L
    G --> L
    H --> L
    F --> L
    L[Sanctions check run automatically]
    L --> M{Sanctions match?}
    M -->|Yes| N[Block transaction, notify manager]
    M -->|No| O[Save customer profile]
    O --> P[CTOS report scheduled if required]
```

---

## Task 6: Transaction Approval Workflow Diagram

**Files:**
- Modify: `resources/views/user-manual/index.blade.php:336-337`

- [ ] **Step 1: Insert diagram after section heading**

```mermaid
flowchart TD
    A[Transaction over RM50,000] --> B[Automatically placed on hold]
    B --> C[Approval request sent to manager]
    C --> D[Manager reviews transaction]
    D --> E{Approve?}
    E -->|Approve| F[Verify transaction details]
    F --> G[Enter MFA code]
    G --> H[Transaction authorized]
    H --> I[Transaction processed automatically]
    E -->|Reject| J[Enter rejection reason]
    J --> K[Transaction cancelled]
    K --> L[Teller notified]
    E -->|Request more info| M[Send message back to teller]
    M --> N[Teller updates transaction]
    N --> C
```

---

## Task 7: Shift Handover Workflow Diagram

**Files:**
- Modify: `resources/views/user-manual/index.blade.php:216-217`

- [ ] **Step 1: Insert diagram after section heading**

```mermaid
flowchart TD
    A[Both tellers present at counter] --> B[Outgoing teller completes pending transactions]
    B --> C[Joint physical cash count]
    C --> D[Counts match?]
    D -->|No| E[Recount together]
    E --> C
    D -->|Yes| F[Outgoing initiates handover]
    F --> G[Outgoing teller enters password]
    G --> H[Incoming teller verifies amounts]
    H --> I[Incoming teller enters password]
    I --> J[Counter custody transferred]
    J --> K[Handover slip printed]
    K --> L[Both sign slip]
    L --> M[Audit log entry created]
```

---

## Task 8: End of Day Closing Workflow Diagram

**Files:**
- Modify: `resources/views/user-manual/index.blade.php:467-468`

- [ ] **Step 1: Insert diagram after section heading**

```mermaid
flowchart TD
    A[All counters closed] --> B[Run branch position summary]
    B --> C[Reconcile counter totals vs vault]
    C --> D{All variances resolved?}
    D -->|No| E[Investigate discrepancies]
    E --> C
    D -->|Yes| F[Print daily transaction report]
    F --> G[Manager verifies all reports]
    G --> H[Sign closing register]
    H --> I[Run EOD batch processes]
    I --> J[Generate BNM reports]
    J --> K[Backup database]
    K --> L[Close business day]
    L --> M[System ready for next day]
```

---

## Final Verification

- [ ] All 8 diagrams are added correctly under each corresponding section
- [ ] Mermaid syntax is valid
- [ ] Blade template still renders correctly
- [ ] Code style follows project conventions
- [ ] All diagrams are using standard flowchart symbols consistent with each other

Plan complete and saved to `docs/superpowers/plans/2026-04-16-user-manual-workflow-diagrams.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
