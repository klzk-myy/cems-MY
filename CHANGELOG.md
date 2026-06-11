# Changelog

All notable changes to this project will be documented in this file.

## [2026-06-11] - Codebase Fixes Consolidation

### Fixed
- `ReportingService::generateDailySummary()` — replaced hardcoded `'Buy'`/`'Sell'` strings in `DB::raw()` with parameter binding via `TransactionType` enum values. Eliminates SQL injection risk (commit `b36aafe`).

### Verified (Already Implemented)
- **G1 Compliance** — `CddLevelDeterminationService` correctly uses risk-based Enhanced CDD (PEP, sanctions, high-risk rating), not amount-based. All 12 tests pass.
- **G2 Compliance** — STR filing deadline logic not applicable; `StrReportService` was removed in P0 cleanup.
- **Sanctions Freeze/Block/Reject** — `CustomerScreeningService::handleConfirmedMatch()` implements pd-00.md 27.6 freeze, block, and reject actions. `Customer` model has `freeze()`/`unfreeze()` methods. All 15 tests pass.
- **Foreign vs Domestic PEP** — `PepType` enum and `CddLevelDeterminationService` correctly distinguish foreign PEP (always Enhanced) from domestic PEP (risk-based). Tests pass.
- **Database Indexes** — Performance indexes on `account_ledger` and `journal_entries` already migrated and active.
- **N+1 Queries** — `TransactionMonitoringService` and `BankReconciliationService` have no loop-based N+1 issues; eager loading is correctly used.
- **XSS** — No `echo` or `{!! !!}` vulnerabilities found in controllers or views.

### Security
- `ReportingService` `DB::raw()` parameter binding (prevents SQL injection risk).

---

## [2026-06-01] - Accounting System Cleanup (P0)

### Added
- Database indexes on `account_ledger` and `journal_entries` for improved query performance.
- Comprehensive `EnhancedChartOfAccountsSeeder` with missing accounts (Nostro, petty cash, fixed assets, etc.).

### Changed
- Updated `AccountingService` to post journal entries directly as `Posted` status with immediate ledger updates.
- Simplified `isDebitAccount()` to use `AccountType` enum with fallback to string check.
- Consolidated seeders: `ChartOfAccountsSeeder` merged into `EnhancedChartOfAccountsSeeder`; `DatabaseSeeder` and `BusinessSetupSeeder` updated.
- Refactored test references to use consolidated seeder.

### Fixed
- Critical bug: missing `AccountType` import in `AccountingService` caused all accounts to be treated as credit-normal, reversing balance logic.
- `OffBalance` accounts now correctly set `normal_balance` in migration.

### Removed
- Deleted dead methods: `submitForApproval()` and `approveEntry()` from `AccountingService` (deprecated).
- Removed all STR (Suspicious Transaction Report) reporting UI and routes:
  - `ComplianceReportingController`
  - Views under `resources/views/compliance/` and `resources/views/pages/compliance/`
  - STR-related routes in `routes/web.php`

### Security
- None
