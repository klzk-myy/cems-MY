# Changelog

All notable changes to this project will be documented in this file.

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
