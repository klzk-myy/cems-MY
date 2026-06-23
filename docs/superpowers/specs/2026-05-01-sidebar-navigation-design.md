# Sidebar Navigation Redesign

**Date:** 2026-05-01
**Goal:** Remove old sidebar and recreate new collapsible dropdown sidebar

## Overview

Replace the current flat sidebar with a collapsible dropdown navigation that includes all authenticated routes organized by section.

## Structure

### Sections

| Section | Icon | Routes | Access |
|--------|------|--------|--------|
| Dashboard | Home | `/dashboard`, `/performance`, `/rates` | All |
| Operations | Cash | `/transactions/*`, `/customers/*` | All |
| Counter | Register | `/counters/*` | All |
| Stock | Boxes | `/stock-cash/*`, `/stock-transfers/*` | Manager+ |
| Compliance | Shield | `/compliance/*`, `/str/*` | Compliance+ |
| Accounting | Book | `/accounting/*` | Manager+ |
| Reports | Chart | `/reports/*` | Manager+ |
| System | Cog | `/users/*`, `/branches/*`, `/audit/*` | Admin |

### Dropdown Behavior
- Click section header → expands submenu
- Click again → collapses
- Active route highlighted with accent color
- Role-based visibility (hide items user can't access based on role middleware)
- Smooth CSS transition animations

## Submenu Items

### Dashboard
- Dashboard → `/dashboard`
- Performance → `/performance`
- Exchange Rates → `/rates`

### Operations
- Transactions → `/transactions`
  - Create → `/transactions/create`
  - Batch Upload → `/transactions/batch-upload`
- Customers → `/customers`
  - Create → `/customers/create`
  - Exchange Rates → `/customers/exchange-rates`

### Counter
- Counters → `/counters`
- Counter History → per-counter via `/{counter}/history`

### Stock
- Stock & Cash → `/stock-cash`
  - Till Report → `/stock-cash/till-report`
  - Reconciliation → `/stock-cash/reconciliation`
- Stock Transfers → `/stock-transfers`
  - Create → `/stock-transfers/create`

### Compliance
- Dashboard → `/compliance`
- Alert Triage → `/compliance/alerts`
- Cases → `/compliance/cases`
- STR Reports → `/str`
- EDD Records → `/compliance/edd`
- Sanctions → `/compliance/sanctions`
  - Entries → `/compliance/sanctions/entries`
  - Import Logs → `/compliance/sanctions/import-logs`
- Risk Dashboard → `/compliance/risk-dashboard`
- Compliance Reporting → `/compliance/reporting`
- AML Rules → `/compliance/rules`
- CTOS Reports → `/compliance/ctos`
- Findings → `/compliance/findings`
- EDD Templates → `/compliance/edd-templates`
- Screening → `/compliance/screening/{customerId}`
- Workspace → `/compliance/workspace`
- Unified Alerts → `/compliance/unified`

### Accounting
- Overview → `/accounting`
- Journal → `/accounting/journal`
  - Create → `/accounting/journal/create`
- Ledger → `/accounting/ledger`
- Trial Balance → `/accounting/trial-balance`
- Profit & Loss → `/accounting/profit-loss`
- Balance Sheet → `/accounting/balance-sheet`
- Cash Flow → `/accounting/cash-flow`
- Ratios → `/accounting/ratios`
- Reconciliation → `/accounting/reconciliation`
- Budget → `/accounting/budget`
- Revaluation → `/accounting/revaluation`
  - History → `/accounting/revaluation/history`
- Month End → `/accounting/month-end`
- Periods → `/accounting/periods`
- Fiscal Years → `/accounting/fiscal-years`

### Reports
- Overview → `/reports`
- MSB2 → `/reports/msb2`
- LCTR → `/reports/lctr`
- LMCA → `/reports/lmca`
- Quarterly LVR → `/reports/quarterly-lvr`
- Position Limits → `/reports/position-limit`
- Monthly Trends → `/reports/monthly-trends`
- Profitability → `/reports/profitability`
- Customer Analysis → `/reports/customer-analysis`
- Compliance Summary → `/reports/compliance-summary`
- Report History → `/reports/history`
- Compare → `/reports/compare`

### System
- Users → `/users` (Admin only)
- Branches → `/branches`
  - Create → `/branches/create`
  - Open → `/branches/open`
- Audit → `/audit`
  - Dashboard → `/audit/dashboard`

## Technical Approach

### Implementation
- Single sidebar component in `layouts/base.blade.php`
- Use `<details><summary>` or Alpine.js for collapsible dropdowns
- CSS classes for active state: `aria-current="page"`
- Role checking via `auth()->user()->role->isManager()`, `isAdmin()`, etc.

### Responsive
- Desktop: sidebar always visible
- Mobile (< 1024px): sidebar hidden, hamburger menu triggers slide-out drawer

## Files to Modify

1. `resources/views/layouts/base.blade.php` - Replace sidebar navigation

## Role Definitions

- **All** = teller, manager, compliance, admin
- **Manager+** = manager, compliance, admin
- **Compliance+** = compliance, admin
- **Admin only** = admin