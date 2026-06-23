# Model & Factory Audit Design

> Fresh full audit of all Eloquent models and factories to identify relationship gaps, schema mismatches, missing factories, and code-quality issues.

## Goal

Produce an accurate inventory of model/factory issues and a prioritized implementation plan to fix them. The previous audit (commit `a45496f5`) addressed many runtime and relationship bugs; this audit re-examines the entire model layer to catch anything that was missed or introduced since then.

## Scope

### In scope

- All Eloquent models under `app/Models/` (≈77 files, including base models and traits).
- All factories under `database/factories/` (≈45 files).
- Eloquent relationships and their inverses.
- `$fillable`, `$casts`, `$hidden`, `$with`, `$dates`, and `$table` alignment with migration schemas.
- Factory coverage, enum usage, and useful states.

### Out of scope

- Controllers, form requests, and API resources unless a model fix forces a change.
- Purely cosmetic refactoring unrelated to bugs or testability.
- Business-logic changes not required by a model/factory defect.

## Methodology

### Step A — Graph extraction (GitNexus)

Query the `cems-my` GitNexus index for:

1. Every concrete model class, its base class, and used traits.
2. Every relationship method, its return type, target class, and foreign-key column.
3. Every factory class and its associated model.

### Step B — Schema cross-check

For each model, compare `$fillable` and `$casts` against the matching migration's columns. Flag:

- `$fillable` fields with no matching column.
- Columns that exist in the migration but are absent from `$fillable` or `$casts`.
- JSON/array columns not cast to `array`.
- Enum columns not cast to their enum class.
- Missing return-type hints on relationship methods.
- Dead casts referencing non-existent columns.

### Step C — Relationship gap analysis

Build a directed relationship graph and flag:

- One-way relationships with no inverse.
- Relationships targeting non-existent models or columns.
- `belongsTo` foreign keys that are nullable in the database but not handled correctly.
- Missing parent/child `HasMany` inverses for obvious pairs (e.g., `User` → `Transaction`).

### Step D — Factory coverage check

For every non-abstract model, verify a factory exists. For existing factories, flag:

- Enum values passed as raw strings instead of enum instances or `->value`.
- Missing convenience states commonly needed by tests.
- Factories referencing fields that no longer exist on the model.
- Models without any factory at all.

## Issue severity

| Severity | Definition | Example |
|----------|------------|---------|
| Critical | Causes runtime errors or data corruption. | Missing import, column name mismatch causing silent write failure. |
| High | Security risk or clear schema mismatch. | Sensitive field not in `$hidden`, enum not cast. |
| Medium | Code quality or missing testability. | Missing return type, missing inverse relationship, missing factory state. |
| Low | Cosmetic or minor inconsistency. | Import ordering, redundant docblock. |

## Deliverables

1. **Audit findings table** listing each issue: file, line, severity, description, recommended fix.
2. **Implementation plan** (via `writing-plans`) broken into phases:
   - Phase 1: Critical runtime fixes.
   - Phase 2: Schema alignment and casts.
   - Phase 3: Relationship completion.
   - Phase 4: Factory/test backfill.
   - Phase 5: Verification and style cleanup.

## Verification

- After each fix, run affected PHPUnit tests with `php artisan test --compact --filter=<Name>`.
- Run `vendor/bin/pint --dirty --format agent` after each batch of changes.
- Final gate: full suite `php artisan test --compact`.
- For schema changes, verify both `migrate` and `migrate:rollback` succeed.

## Risks & exclusions

- **Intentional legacy bridging:** Some models use accessors/mutators to bridge old and new column names (e.g., `CurrencyPosition`). These will be flagged and verified, not blindly changed.
- **Mixed-data JSON columns:** Columns like `SanctionEntry.details` contain mixed string and JSON data. They will not be forced to an `array` cast unless the data is cleaned first.
- **Production safety:** Destructive column renames require forward migrations that preserve existing data.

## Success criteria

- All critical and high issues have fixes planned or implemented.
- Every non-abstract model has a matching, usable factory.
- Relationship graph is complete enough that `Model::with()` calls do not trigger N+1 regressions.
- Full test suite passes after all fixes.
