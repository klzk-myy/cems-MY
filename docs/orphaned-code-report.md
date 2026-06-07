# Orphaned Code Detection Report

> **Generated:** 2026-06-02
> **Method:** Hybrid (GitNexus knowledge graph + custom PHP/bash scripts)
> **Scope:** CEMS-MY Laravel 10 banking system — 6 layers

## Summary

| Layer | Candidates | Confidence |
|-------|-----------|------------|
| PHP Classes & Methods | 3 orphaned services | High |
| Routes | 0 broken/orphaned | High |
| Blade Views | 11 orphaned views | High |
| Database Schema | 0 orphaned tables/columns | Medium |
| Enums & Config | 1 orphaned enum | High |
| Frontend Assets | 0 orphaned files | Medium |

---

## 1. PHP Classes & Methods

### `app/Services/AmlRuleService.php`
- **Orphaned:** Entire class — zero references in app/, tests/, routes/, config/, or resources/
- **Confidence:** High (GitNexus graph verified with cross-repo grep)
- **Risk:** Low
- **Suggested action:** Remove

### `app/Services/CashFlowService.php`
- **Orphaned:** Entire class — zero references outside its own file
- **Confidence:** High (grep across entire codebase found only self-references)
- **Risk:** Low
- **Suggested action:** Remove

### `app/Services/PerformanceAlertingService.php`
- **Orphaned:** Entire class — zero references in app code (only a test file references it)
- **Confidence:** High
- **Risk:** Low
- **Suggested action:** Remove (or keep if alerts will be implemented)

### Methods (filtering notes)
- 493 methods returned with zero CALLS edges in graph. After filtering false positives (model dynamic access, command `handle()`, job dispatch, event listeners, constructor injection, policies, form requests, blade components), the only truly orphaned methods belong to the 3 classes above.

---

## 2. Routes

- **Total routes checked:** ~170+ across `api_v1.php`, `auth.php`, `web.php`
- **Missing controllers:** 0 (all referenced controllers exist)
- **Broken route handlers:** 0
- **Orphaned routes (zero frontend consumers):** Route map returned empty consumer data for all routes (GitNexus does not track Blade-level route usage). Routes are used via server-rendered Blade views, not a separate frontend SPA, so no action needed.

---

## 3. Blade Views

- **Total views scanned:** 115
- **Referenced views:** 99
- **Orphaned candidates (non-component):** 11

### `resources/views/customers/kyc.blade.php`
- **View:** `customers.kyc`
- **Confidence:** High

### `resources/views/accounting/month-end.blade.php`
- **View:** `accounting.month-end`
- **Confidence:** High

### `resources/views/compliance/edd-templates/index.blade.php`
- **View:** `compliance.edd-templates.index`
- **Confidence:** High

### `resources/views/compliance/edd-templates/show.blade.php`
- **View:** `compliance.edd-templates.show`
- **Confidence:** High

### `resources/views/compliance/reporting/schedule.blade.php`
- **View:** `compliance.reporting.schedule`
- **Confidence:** High

### `resources/views/auth/change-password.blade.php`
- **View:** `auth.change-password`
- **Confidence:** High (may be rendered via route — verify before deleting)

### `resources/views/transactions/customer-history.blade.php`
- **View:** `transactions.customer-history`
- **Confidence:** High

### `resources/views/pages/performance.blade.php`
- **View:** `pages.performance`
- **Confidence:** High

### `resources/views/pages/audit/index.blade.php`
- **View:** `pages.audit.index`
- **Confidence:** High

### `resources/views/pages/branches/index.blade.php`
- **View:** `pages.branches.index`
- **Confidence:** High

### `resources/views/pages/rates/index.blade.php`
- **View:** `pages.rates.index`
- **Confidence:** High

### False positive: 9 Blade components
Used via `<x-*>` syntax which the script doesn't track:
`components.progress-bar`, `components.chart-bar`, `components.card`, `components.card-section`, `components.table`, `components.stat-card`, `components.badge`, `components.page-header`, `components.button`

---

## 4. Database Schema

- **Tables scanned:** ~50+
- **Orphaned tables:** 0
- **Orphaned columns:** 0

All tables and columns in the schema are referenced somewhere in application code.

---

## 5. Enums & Config

### Enum: `CustomerIdType`
- **File:** `app/Enums/CustomerIdType.php`
- **Usage:** Zero references outside its own file
- **Confidence:** High
- **Suggested action:** Remove

### Config keys
- **Keys flagged:** 138 "unused" but majority are false positives (Laravel framework internals accessed via `env()`, facades, or array helpers rather than `config('...')`)
- **Notable config keys to review:** None confirmed as truly orphaned after filtering framework defaults

---

## 6. Frontend Assets

- **JS files:** Only `app.js` and `bootstrap.js` (both entry points — active)
- **CSS files:** Only `app.css` (entry point — active)
- **Orphaned assets:** 0

---

## Action Plan

| Tag | Count | Items |
|-----|-------|-------|
| **SAFE TO DELETE** | 4 | `AmlRuleService`, `CashFlowService`, `PerformanceAlertingService`, `CustomerIdType` enum |
| **NEEDS REVIEW** | 11 | Orphaned views (verify each is truly unused before deleting) |
| **BROKEN ROUTE** | 0 | — |

### Recommended Next Steps

1. **Review SAFE TO DELETE items** — Remove in a cleanup PR:
   - Delete `app/Services/AmlRuleService.php`
   - Delete `app/Services/CashFlowService.php`
   - Delete `app/Services/PerformanceAlertingService.php`
   - Delete `app/Enums/CustomerIdType.php`

2. **Review NEEDS REVIEW views** — For each of the 11 views:
   - Check if it's rendered via a named route or controller that the scan missed
   - Check git history for when it was last modified
   - Check if any other Blade file includes it via dynamic name (`@include($variable)`)

3. **Review false-positive components** — The 9 components used via `<x-*>` syntax: confirm they're rendered by any parent view

4. **Run test suite** after any deletions to confirm nothing breaks