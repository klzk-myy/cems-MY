# Codebase Fault Analysis & Architecture Gaps Report

## 1. Logical and Functional Faults

### 1.1 Concurrency Risk and Stock Overselling in Transactions
- **Location:** `TransactionService.php` (`createTransaction` and `approveTransaction`)
- **Fault:** When a transaction requires manager approval (e.g., $\ge$ RM 50,000), its status is set to `Pending` or `PendingApproval`, and it does **not** deduct the stock balance immediately. However, when the manager subsequently approves the transaction via `approveTransaction`, the service applies the stock deduction `updatePosition()` **without checking if the stock is still available**.
- **Impact:** While the transaction was pending, another customer could have bought the remaining stock. Once the pending transaction is approved, it forces a deduction, leading to negative foreign currency inventory. 

### 1.2 Incomplete Double-Entry on Till Balances (Missing Local Currency Updates)
- **Location:** `TransactionService.php` (`updateTillBalance`)
- **Fault:** When a `Buy` or `Sell` occurs, the system only fetches and updates the `TillBalance` belonging to the foreign currency being transacted. The MYR (local currency) till balance is never updated during this phase.
- **Impact:** The physical MYR cash drawer movement (cash in during 'Sell' or cash out during 'Buy') is entirely unrecorded in the Till Balance component. At the end of the day, the MYR closing balance will not reflect actual trades, rendering the `CounterService` variance checks artificially skewed or broken for MYR.

### 1.3 Audit Log Table Lock Contention
- **Location:** `AuditService.php` (`logWithSeverity`)
- **Fault:** To perform tamper-proof hash chaining, `AuditService` forcefully issues `SystemLog::orderBy('id', 'desc')->lockForUpdate()->first();` inside a synchronous DB transaction.
- **Impact:** Because almost every action in the application triggers an audit log, this forces the application to serialize **all** system actions globally across all users. This lock will become a catastrophic performance bottleneck, leading to massive lock wait timeouts as concurrent traffic scales.

### 1.4 Hardcoded Currency and Inconsistent Data Types
- **Location:** `CounterService.php` 
- **Fault:** Variables like `VARIANCE_THRESHOLD_RED` and `VARIANCE_THRESHOLD_YELLOW` are defined as floats (`500.00`), but are cast to strings inside `BcmathHelper` checks. Depending on PHP locale and runtime, float-to-string casting can trim decimals, which crashes strict BCMath operations.
- **Fault:** Hardcoded string `'MYR'` is heavily used throughout `initiateHandover` rather than looking up the base reporting currency dynamically from configuration.

## 2. Gaps Between Current Codebase and Mature Production Systems

### 2.1 Unsearchable Encrypted PII (Blind Indexing Missing)
- **Gap:** In `CustomerController.php`, the system notes: `// Encrypted fields cannot be searched via SQL LIKE`. Consequently, tellers/managers cannot look up a customer by their ID Number (IC/Passport) because the field `id_number_encrypted` is randomly encrypted.
- **Standard:** Mature financial production systems utilize **Blind Indexing** (e.g., storing a deterministic HMAC hash of the ID number in a separate `id_number_hash` column) to allow exact match lookups while keeping the raw data encrypted. 

### 2.2 Lack of Fine-Grained Exception Handling
- **Gap:** Core services throw base `\Exception` or `\RuntimeException` (e.g., "Counter is already open today"). 
- **Standard:** The codebase lacks Domain/Business Exceptions such as `InsufficientInventoryException` or `TillAlreadyOpenException`. Using generic exceptions forces the controller to rely on catching blanket `\Exception` blocks, which risks swallowing critical runtime errors alongside standard business validation errors.

### 2.3 Resiliency & Event-Driven Audit Trails
- **Gap:** Features like `ctosReportService->createFromTransaction()` run inline within the main DB transaction but are `try/catch`ed to swallow errors quietly. 
- **Standard:** A mature system delegates third-party integration reports, heavy synchronous ledger operations, and audit log hash-chaining to robust asynchronous queues (e.g., RabbitMQ, Kafka, or Laravel Horizon) with retry mechanisms and dead-letter queues. 

## 3. Recommended Improvements

1. **Implement Pending Stock Reservations:** 
   Modify `createTransaction` to create an "Encumbered/Reserved" stock entry when an approval is pending. Alter `approveTransaction` to finalize the reservation or fail gracefully if the inventory delta breaches limits.
   
2. **Fix Till Balance Mutator Math:** 
   Update `TransactionService` so that every foreign currency buy/sell fetches **both** the foreign currency till balance row AND the local currency (MYR) till balance row, updating both appropriately to reflect physical cash movements.

3. **Asynchronous Audit Logging:** 
   Remove the synchronous `lockForUpdate()` from `AuditService`. Instead, generate and insert logs normally with a unique ID, and run a scheduled background job or asynchronous worker to periodically calculate and seal the cryptographic hash chains of recent logs.

4. **Blind Indexes for KYC Search:** 
   Add an `id_number_hash` column to the `customers` table. Hash incoming search queries deterministically to support exact-match searches for existing customers via their IC or Passport number.

5. **Introduce Domain Exceptions:** 
   Implement specific exception classes in `app/Exceptions` rather than throwing `\Exception`, ensuring API/Web handlers only catch expected business violations while letting critical system crashes properly alert the developers.
