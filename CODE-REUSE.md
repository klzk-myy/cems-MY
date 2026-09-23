# Code Reuse Findings

Entry format (append at the bottom, never rewrite or reorder previous
entries; mark resolved entries `status: done (<commit>)`):

```
## [YYYY-MM-DD] <short title>
- where: <file:symbol or files>
- finding: <duplication / parallel implementation / dead config / divergent path>
- status: open | done (<commit>) | wontfix (<reason>)
```

---

## [2026-09-23] Reports dashboard KPI metrics computed in SQL, not PHP
- where: `app/Services/Reporting/ReportSchedulingService.php` (`calculateAvgFlagResolutionTime`, `calculateEddCompletionRate`, `calculateReportsOnSchedule`, `getFileMeta`)
- finding: The reports dashboard KPI cards loaded collections into PHP (all resolved flags of 30 days, all report runs of 7 days) to compute averages and counts — work the database does in one query. `getFileMeta()` read the entire CSV into memory just to count lines. SQL aggregates + streamed line counting make all four flat.
- status: done (commit `6b4d1de9`)

## [2026-09-23] MSB2 report page served from cache with SQL avg-rate
- where: `app/Services/Reporting/Generators/Msb2ReportGenerator.php`
- finding: The MSB2 page re-aggregated the full day's transactions on every load (4 queries + hydration of every transaction for a PHP rate-averaging loop). Past-date data is deterministic, so cache-aside with a `reports` tag (invalidated on transaction write) serves page loads from cache. Rate averaging moved to SQL `AVG(CASE...)`.
- status: done (commit `ce65bc12`)

## [2026-09-23] Playwright helper extraction prevents spec triplication
- where: `tests/100-transactions.spec.ts`, `tests/200-transaction-lifecycle.spec.ts`, `tests/300-branch-ops.spec.ts`, `tests/support/helpers.ts`
- finding: The original volume spec inlined all shared logic (login/logout,
  stateful API calls, counter-session provisioning, customer/transaction
  creation, data generators). The new lifecycle and branch-ops specs need
  the same logic — without extraction it would have been triplicated across
  three files. Additionally, the 300 spec initially carried a local copy of
  the counter-close logic that `ensureCounterSession` also needed; it was
  hoisted into the shared `closeOpenCounterSessions` helper.
- status: done (this change set — helpers extracted before the new specs
  were written; both specs import from `tests/support/helpers.ts`)

## [2026-09-23] Pool top-up logic reused between the provisioning and remittance specs
- where: `tests/support/helpers.ts` (`ensurePoolBalances`, `ensurePoolAvailable`)
- finding: The 400 spec's pool-remittance test needed the same
  fund-the-shortfall logic as the counter-session provisioning, but for a
  single currency with a custom amount. Extracted `ensurePoolAvailable` as
  the shared primitive instead of duplicating the fund-form flow.
- status: done (this change set — `ensurePoolBalances` now delegates to it)

## [2026-09-23] Four model factories lacked `@extends` generics
- where: `database/factories/TestResultFactory.php`, `Compliance/EnhancedDiligenceRecordFactory.php`, `BudgetFactory.php`, `BranchPoolFactory.php`
- finding: Without `@extends Factory<TModel>`, `create()` statically types
  as the base `Model`, so property access in tests fails PHPStan level 7 —
  previously masked by the baseline. Adding the generics (docblock-only)
  lets new tests type-check without baselining.
- status: done (this change set)

## [2026-09-23] Stale backup file next to the active spec
- where: `tests/100-transactions.spec.ts.bak`
- finding: An 891-line stale backup of the spec sat in `tests/` (gitignored
  but present on disk) — drift risk when editing the wrong file.
- status: done (this change set — file deleted)
