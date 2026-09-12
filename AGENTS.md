<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.30
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/dusk (DUSK) - v8
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

## Database Schema (migration-free)
- The `database/migrations/` directory has been retired. `database/seeders/SchemaSeeder.php` is the single source of truth for the schema (85 tables + reference data).
- Do not create new migrations. Schema changes are made by editing `SchemaSeeder.php` directly.
- `SchemaSeeder` is destructive: it drops every table and recreates them. It must only run against empty databases through the guarded call sites (`business:setup --fresh` with confirmation, `SetupController` pre-setup schema creation, `ResetTestDatabase --fresh`, and the test bootstrap).
- Fresh install: `php artisan db:seed --class=SchemaSeeder`. The seeder also drops the legacy `migrations` bookkeeping table when present.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

## PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.

<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>

### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **cems-MY**.

> Index stale? Run `node .gitnexus/run.cjs analyze --index-only` from the project root — it auto-selects an available runner. No `.gitnexus/run.cjs` yet? Bootstrap with `npx`, `bunx`, or `pnpm dlx` — e.g. `bunx gitnexus@latest analyze` (npm 11 npx crash; #1939).

## Always Do

- **MUST run impact before editing.** Use `impact({target: "symbolName", direction: "upstream"})` or `node .gitnexus/run.cjs impact "symbolName" --direction upstream --repo .`; report callers, processes, and risk. Never substitute grep for graph analysis.
- **MUST analyze graph changes before committing.** Use `detect_changes({scope: "all"})` (MCP) or `node .gitnexus/run.cjs detect-changes --scope all --repo .` (CLI fallback). `partial: true` or `truncated: true` is not a clean check — a zero means unseen, not unaffected; re-run it. For regression review: `detect_changes({scope: "compare", base_ref: "main"})` or `node .gitnexus/run.cjs detect-changes --scope compare --base-ref "main" --repo .`.
- MUST warn on HIGH/CRITICAL `risk` pre-edit; never use `riskSharedAxes` to waive a HIGH/CRITICAL `risk` warning. Compare File/symbol: MCP File omits axes; Graph-RAG expands File.
- **MUST treat `risk: UNKNOWN` as unresolved, not as low.** An empty caller set is not evidence the symbol is unused — it can also mean the callers are not resolvable by the index (plain-object property access, dynamic dispatch, cross-language calls). `impact` pairs `UNKNOWN` with a `riskNote` saying so. Confirm with a text search before treating the symbol as safe to change or delete; do not proceed on the strength of a zero.
- **MUST use `query({search_query: "concept"})` for concepts/flows, `context({name: "symbolName"})` for a named symbol, or `impact` for blast radius, on read-only callers, dependencies, imports, or execution flow.** Graph first; text search only for empty/`UNKNOWN`/literals.
- For security review, `explain({target: "fileOrSymbol"})` lists taint findings (source→sink flows; needs `analyze --pdg`).

## Never Do

- NEVER edit a function, class, or method before MCP/CLI impact analysis.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis, and never read `UNKNOWN` as an all-clear — it means the walk could not answer, which is the one verdict that requires confirming by other means.
- NEVER rename symbols with find-and-replace — use `rename` which understands the call graph.
- NEVER commit before MCP/CLI graph change analysis.

## Resources

| Resource | Use for |
| --- | --- |
| `gitnexus://repo/cems-MY/context` | Codebase overview, check index freshness |
| `gitnexus://repo/cems-MY/clusters` | All functional areas |
| `gitnexus://repo/cems-MY/processes` | All execution flows |
| `gitnexus://repo/cems-MY/process/{name}` | Step-by-step execution trace |

<!-- gitnexus:end -->

---

## Table of Contents

1. [GitNexus — Code Intelligence](#1-gitnexus--code-intelligence)
2. [Project Architecture](#2-project-architecture)
3. [Key Services & Patterns](#3-key-services--patterns)
4. [Redis Directives](#4-redis-directives)
5. [Workflow: Edit & Deploy](#5-workflow-edit--deploy)
6. [Testing](#6-testing)
7. [Debugging](#7-debugging)
8. [Known Issues](#8-known-issues)
9. [Configuration](#9-configuration)
10. [Changelog Requirements](#10-changelog-requirements)
11. [Working Principle](#11-working-principle)

---

## 1. GitNexus — Code Intelligence

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **cems-my** (15,046 symbols, 42,964 relationships, 300 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> Index stale? Run `npx gitnexus analyze` from the project root. It auto-selects an available runner. npm 11 crash → `npm i -g gitnexus` (#1939).

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows. For regression review, compare against the default branch: `detect_changes({scope: "compare", base_ref: "main"})`.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `query({search_query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `context({name: "symbolName"})`.
- For security review, `explain({target: "fileOrSymbol"})` lists taint findings (source→sink flows; needs `analyze --pdg`).

## Never Do

- NEVER edit a function, class, or method without first running `impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `rename` which understands the call graph.
- NEVER commit changes without running `detect_changes()` to check affected scope.

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/cems-my/context` | Codebase overview, check index freshness |
| `gitnexus://repo/cems-my/clusters` | All functional areas |
| `gitnexus://repo/cems-my/processes` | All execution flows |
| `gitnexus://repo/cems-my/process/{name}` | Step-by-step execution trace |

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->

---

## 2. Project Architecture

### Stack
- **PHP 8.3**, Laravel 12
- **SQLite** (testing, `:memory:` / file-backed) + **Redis** (cache, queues via Laravel Horizon)
- Single monolith — no sharding. All state lives in one schema.

### Redis Environment
- phpredis via Laravel `Redis` facade; Horizon stores job metadata in Redis.
- Cache prefix: `config('cache.prefix')` → `cems-my_cache_` (from `config/cache.php`).
- Horizon prefix: `config('horizon.prefix')` → `cems-my_horizon:` (from `config/horizon.php`).
- **Caveat:** `rawCommand` bypasses `OPT_PREFIX` — prepend manually.

### Order Flow (transaction lifecycle)
1. `POST /transactions` (web) or `POST /api/v1/transactions` (API) → `TransactionController` → `TransactionCreationService::prepareAndCreate()`
2. Validation (`TransactionValidationInterface`) → stock reservation → position decrement → journal entries (`TransactionAccountingService`)
3. `TransactionCreated` event → listeners → compliance screening, alert generation
4. `POST /transactions/{id}/approve` → `TransactionApprovalService` → state machine transition
5. Cancellation flow: `requestCancellation` → `approveCancellation` / `rejectCancellation`

### Sharding
- **None.** CEMS-MY is single-schema. Ignore any shard-specific language from upstream docs.

### Simulation Harness (consumer-only)
- See §11. A black-box HTTP harness driven by `php artisan simulation:run`. Strictly a consumer: no direct service calls, no `DB::`, no tinker. Checks results via **both** web routes (session + CSRF) and API v1 routes (Sanctum + JSON), diffing derived DB state to prove both surfaces write identical truth.

---

## 3. Key Services & Patterns

### Services

| Service | Location | Purpose |
|---------|----------|---------|
| `TransactionCreationService` | `app/Services/Transaction/` | Stock reservation, position decrement, journal entries, idempotency |
| `TransactionApprovalService` | `app/Services/Transaction/` | State machine transitions (approve/reject/confirm) |
| `TransactionCancellationService` | `app/Services/Transaction/` | Cancellation request → approve/reject |
| `TransactionValidationService` | `app/Services/Transaction/` | Currency, till balance, IP, KYC, PEP, sanctions, structuring |
| `TransactionAccountingService` | `app/Services/Accounting/` | Double-entry journal creation for every transaction |
| `LedgerService` | `app/Services/Accounting/` | Trial balance, P&L, balance sheet (high-precision math) |
| `CurrencyPositionService` | `app/Services/Accounting/` | Position decrement/increment with locking |
| `TellerAllocationService` | `app/Services/Branch/` | Daily teller stock allocation |
| `TillService` / `TillBalanceManager` | `app/Services/Branch/` | Counter till open/close, balance reconciliation |
| `CounterService` | `app/Services/Branch/` | Counter open/close/handover/emergency |
| `BranchClosingService` | `app/Services/Branch/` | Branch EOD close: initiate → settle → finalize |
| `ComplianceService` | `app/Services/Compliance/` | Screening, alert triage, case management, risk scoring |
| `AuditService` | `app/Services/AuditService.php` | Immutable audit trail |
| `AuditTrailHelper` | `app/Services/Audit/` | Audit trail helper |
| `CacheInvalidationService` | `app/Services/System/` | Cache invalidation on state change |
| `MathService` | `app/Services/System/` | High-precision decimal math (no float errors) |

### Models
- `Transaction` — core transaction, status state machine (`TransactionStatus` enum, 13 values / 10 active)
- `Customer` — KYC, CDD level (`CddLevel`), risk rating (`RiskRating`), PEP, sanctions
- `Branch` / `Counter` / `CounterSession` / `TellerAllocation` / `TillBalance` — branch & counter ops
- `CurrencyPosition` / `JournalEntry` / `JournalLine` / `ChartOfAccount` — double-entry accounting
- `StockReservation` / `StockTransfer` / `StockTransferItem` — physical stock tracking
- `FlaggedTransaction` / `Alert` / `ComplianceCase` (in `app/Models/Compliance/`) / `ComplianceFinding` / `ScreeningResult` — AML/compliance
- `ExchangeRate` / `ExchangeRateHistory` — daily FX rates
- `BranchPool` / `AccountingPeriod` / `FiscalYear` — budgeting & period close

---

## 4. Redis Directives

### Redis Key Families (KeyRegistry)
| Prefix | Type | Purpose |
|--------|------|---------|
| `cems-my_cache_:{tag}:{key}` | STRING/VALUE | Application cache (auto-prefixed via `Cache`) |
| `cems-my_horizon:` + keys | HASH/SET/ZSET | Horizon job metadata, job payloads, sorted sets |
| `cems-my_horizon:notifications:*` | LIST | Notification payloads |
| `cems-my_horizon:failed_jobs` | LIST | Failed job ids |
| `cems-my_horizon:recent_jobs` | LIST | Recent job ids |

### Redis Usage (reality check)
- The app uses the Laravel `Redis` facade (`Redis::get/incr/set/info`) and `Cache` facade (`Cache::forget`, `Cache::store()->flush()`) — **no Lua scripts, no `rawCommand`, no `Redis::command()`** anywhere in `app/`.
- `CacheInvalidationService` uses `Cache::forget()` per key and falls back to `Cache::store()->flush()` when the store does not support tags (logs a warning).
- `CacheMonitoringService` tracks hit/miss counters + memory via `Redis::info('memory')`.
- `QueueHealthCheck` pings Redis, reads queue lengths via `Redis::lrange`, and inspects the prefix via `Redis::connection()->client()->getOption(Redis::OPT_PREFIX)`.
- `ClearStuckQueues` scans queue keys with `Redis::lrange` and unlinks stale job payloads.
- All multi-key work here is sequential facade calls, **not** atomic pipelines. If atomicity is ever required, wrap in a Lua script (see directives below).

### Redis Directives (mandatory)

1. **Prefix discipline** — `rawCommand()` and `Redis::command()` do NOT auto-apply `OPT_PREFIX`. When using them, prepend `config('database.redis.options.prefix', '')` to every key. `Redis::` facade methods (get/set/eval/hset/etc.) DO auto-prefix.

2. **Atomicity first** — Any operation that reads-then-writes multiple keys MUST be a single Lua script. Pipelines are NOT atomic (interleaving possible). Cache invalidation, queue health checks, and monitoring snapshots all require atomic multi-key updates.

3. **Fail closed** — Redis errors in security-critical paths (rate limiting, IP blocking, cache invalidation) must reject/block, never allow. Catch exceptions and return safe defaults.
   - **Nuance:** `StrictRateLimit`'s hard window cap (`tooManyAttempts`) fails closed (429 + `Retry-After`). Only the *burst-smoothing* sub-check (`checkBurst`) is fail-open by design — it logs and continues so a burst-backend outage never blocks legitimate traffic.

4. **Memory bounds** — Every persistent Redis key MUST have a TTL. Exceptions: sequence counters (monotonic), Horizon-managed keys. Caches (dashboard, customer, rates) MUST have LRU eviction or TTL.

5. **Lock namespace** — All lock keys MUST use the configured prefix so they are visible to recovery scans.

6. **Non-blocking deletes** — Never use `DEL` on large key sets. Use `UNLINK` (async) in recovery/reset paths to avoid freezing Redis.

7. **Error differentiation** — `rawCommand('EVAL', ...)` returns `false` on failure. Never cast directly to `(int)` — check for `false` first to distinguish errors from valid `0` returns (e.g., idempotency hits).

8. **DB/Redis ordering** — Write idempotency markers AFTER successful DB commit, not inside the transaction. Otherwise a DB rollback leaves a marker that blocks retries.

9. **Static state in long-lived processes** — Never store unbounded request-derived data in static properties. Long-lived workers require LRU eviction with a fixed maximum size.

### Rate Limiting
- Config: `config/ratelimit.php` → store = `redis` by default (`RATE_LIMIT_CACHE_STORE`).
- Middleware: `throttle:60,1`, `throttle:sensitive`, `throttle:export`, `throttle:5,10` (per-route).
- Dimensions: per-user, per-IP, per-export. In tests, `RATE_LIMIT_CACHE_STORE=array` disables Redis-backed limiting.

---

## 5. Workflow: Edit & Deploy

### Before Editing
1. Run `gitnexus_impact({target: "symbol", direction: "upstream"})`
2. Check for HIGH/CRITICAL risk — warn user if found
3. Search for existing code: `gitnexus_query({query: "concept"})`
4. Check sibling files for reusable patterns

### After Editing
1. Run targeted tests: `php artisan test --filter="<related>"`
2. Verify scope: `gitnexus_detect_changes()`
3. Format: `vendor/bin/pint --dirty --format agent`
4. Analyse: `vendor/bin/phpstan analyse app/<edited-path>`
5. Update CHANGELOG.md (see §10)

### Task Completion
When a task is implemented, always finish with these steps in order:
1. **Code review** — re-read every file you changed; check for correctness, edge cases, consistency with existing conventions, and leftover debug/scratch artifacts.
2. **Partial tests** — run the tests that cover the code you edited (e.g. `php artisan test --filter="<related>"` or the specific test files), not the whole suite.
3. **Commit and push** — stage the changed files, commit with a message explaining *why* (matching existing commit style), then `git push` to the remote.

### Critical Commands
```bash
# Development
php artisan serve
php artisan queue:work redis --queue=high,default,low,compliance,audit
php artisan horizon

# Database
php artisan db:seed --class=SchemaSeeder
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=TransactionSeeder
php artisan ResetTestDatabase --fresh

# Cache / Views
php artisan cache:clear
php artisan route:cache
php artisan view:clear

# === Data Integrity & Recovery (new 2026-09-03) ===
php artisan simulation:run --wave=A --surface=both
php artisan simulation:run --wave=B --surface=web
php artisan simulation:run --wave=C --surface=api
php artisan db:reset-test --fresh
php artisan queue:clear-stuck
php artisan queue:health-check
php artisan CacheMonitoringService::snapshot
php artisan audit:verify
php artisan routes:validate
php artisan transactions:recover {reference}
```

### CI/CD
- CI runs via `scripts/ci/*.sh` (lint.sh, security.sh, test.sh, pipeline.sh) + `Makefile` (`make ci`, `make deploy ENV=staging|production`).
- `phpunit.xml` forces test isolation: `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`, `CACHE_DRIVER=array`, `RATE_LIMIT_CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `SESSION_DRIVER=array`. The `force="true"` attributes are required because the deployment shell exports `DB_CONNECTION=mysql` / `DB_DATABASE=cems_my_staging` and PHPUnit's `<env>` silently skips any variable already present in the shell.
- Run `composer install --no-dev` for production.

---

## 6. Testing

### Suites
| Suite | Command | Notes |
|-------|---------|-------|
| Unit | `php artisan test --testsuite=Unit` | Fast, no DB |
| Feature | `php artisan test --testsuite=Feature` | SQLite + Redis |
| Simulation | `php artisan simulation:run --wave=A|B|C --surface=web|api|both` | Consumer harness, both surfaces |

### Key Tests
```bash
php artisan simulation:run --wave=A --surface=both   # happy-path business-day sweep
php artisan simulation:run --wave=B --surface=both   # edge/attack (expect rejection)
php artisan simulation:run --wave=C --surface=both   # cross-surface parity diff
vendor/bin/phpunit --filter=Simulation --group=wave-b  # waves map to PHPUnit groups
```

### Running Tests
- Run all tests: `php artisan test --compact`
- Run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`
- Filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file)

### Simulation Harness
- Driven by `php artisan simulation:run` — a **strict HTTP consumer**: no direct service calls, no `DB::`, no tinker. State is asserted via `SimulationOracle` (SELECT-only) or HTTP responses only.
- Waves: **A** (`tests/Http/Simulation/WaveA/`, one ordered 32-step business-day sweep), **B** (`tests/Http/Simulation/WaveB/`, 10 independent edge/attack scenarios, each asserting HTTP rejection + zero derived state), **C** (`tests/Http/Simulation/WaveC/`, cross-surface parity: identical booking+approval workflow on web then API, normalized snapshots of transaction row + journal lines + currency-position deltas diffed with strict equality).
- `--wave` maps to PHPUnit groups (`wave-a|wave-b|wave-c` via `#[Group]` attributes); `--surface` is exported as `SIM_SURFACE` and read by `SimulationTestCase::surfaceAllows()`. Paired steps are gated via `runOnSurface()`; single-surface steps always run.
- Note: Laravel bypasses `VerifyCsrfToken` under `runningUnitTests()`, so in-process CSRF probing is impossible — Wave B attacks the session boundary instead (forged session cookie).
- Artifacts: JSON report → `storage/simulation/run.json` (configurable via `--report`).

---

## 7. Debugging

```bash
# Check transaction state
php artisan transactions:recover {reference}

# Check audit chain integrity
php artisan audit:verify

# Check route consistency (web vs API parity)
php artisan routes:validate

# Check queue health
php artisan queue:health-check

# Check cache monitoring
php artisan CacheMonitoringService::snapshot

# Purge stuck queues
php artisan queue:clear-stuck

# Reset test database
php artisan db:reset-test

# Run simulation waves
php artisan simulation:run --wave=A --surface=both
php artisan simulation:run --wave=B --surface=web
php artisan simulation:run --wave=C --surface=api
```

---

## 8. Known Issues

1. **Fat models** — `Customer.php` and `User.php` import services in docblocks (minor; not actual usage). See `full_project_architecture_audit.md` §3.1.
2. **Missing scopes** — `Transaction`, `FlaggedTransaction`, `Alert` could benefit from common query scopes. Low priority.
3. **Controller depth** — `DashboardController` builds complex cached queries directly rather than delegating to a service. Acceptable for dashboards; could extract to `DashboardService`.
4. **Service depth** — `CustomerService` (571 lines) handles creation, encryption, screening, risk scoring, audit logging, caching. Consider splitting into action classes.
5. **N+1 queries** — `CustomerController::show()` loads relationships individually. Use eager loading (`->with()`).
6. **No CHANGELOG.md** — Project has no changelog; see §10 for the required format once adopted.
7. ~~**Redis prefix handling**~~ — **Not applicable.** CEMS-MY uses the Laravel `Redis` facade and `Cache` mostly; the three files using `rawCommand` (`QueueHealthCheck`, `ClearStuckQueues`, `CacheMonitoringService`) already wrap in Lua scripts that handle prefixing internally. Verify before adding `OPT_PREFIX` manual prepends.
8. ~~**Non-atomic pipelines**~~ — **Not applicable.** All multi-key Redis operations are atomic Lua scripts (`invalidateTags`, `snapshot`, `check`, `purge`).
9. ~~**Rate limiter fail-open**~~ — **Not applicable.** `config/ratelimit.php` defaults to `redis`; the `throttle` middleware is Laravel's built-in, which fails closed.
10. ~~**Unbounded memory**~~ — **Not applicable.** Static state is avoided; caches use `remember()` with TTLs via `CacheInvalidationService`.
11. ~~**Migration order**~~ — **Not applicable.** `database/migrations/` is retired; `SchemaSeeder.php` is the single source of truth (85 tables).

### Audit-vs-Codebase Discrepancies
The `full_project_architecture_audit.md` (dated 2026-08-30) lists several items as **open** that are already resolved in the codebase:
- **M8 (missing per-user rate limit key)** — **Fixed** same day in commit `3122c465` ("feat(arch): add user ID to rate limit key (M8, O10)"). `RateLimitService::getRateLimitKey()` now returns `{$limiter}:user:{$userId}` for authenticated requests, `{$limiter}:ip:{$ip}` otherwise.
- **Redis prefix / non-atomic pipelines / fail-open / unbounded memory** — all struck-through in §8 above; the audit still lists them as open.
- **Migration order** — `database/migrations/` was retired after the audit; `SchemaSeeder.php` is the single source of truth.

---

## 9. Configuration

| File | Purpose |
|------|---------|
| `config/cache.php` | Cache stores + `cems_my_cache_` prefix |
| `config/database.php` | SQLite (testing) + Redis connection + prefix |
| `config/horizon.php` | Horizon Redis prefix (`cems_my_horizon:`), queue connections |
| `config/ratelimit.php` | Rate-limit cache store (`redis` default) |
| `config/sanctum.php` | Personal access token config for API v1 |
| `config/cems.php` | Application-specific CEMS settings |
| `config/compliance.php` | Compliance thresholds & rules |
| `config/thresholds.php` | Transaction thresholds (CDD, large, structuring) |
| `config/transactions.php` | Transaction business rules |
| `config/pos.php` | Point-of-sale / counter config |
| `config/security.php` | IP blocking, security headers |

---

## 10. Changelog Requirements

Every edit to `app/`, `config/`, `database/`, `routes/`, `tests/` must have an entry:

```
## [YYYY-MM-DD] - <Short Description>

### Files Changed
- `path/to/file.php` - what changed

### Purpose
Why this change was made

### Changes Made
- Specific change 1
- Specific change 2

### Testing
- Commands run and results

### Impact Analysis
- GitNexus blast radius for HIGH/CRITICAL changes

---
```

Rules: ISO date • newest first • never modify previous entries • group related changes

---

## 11. Working Principle

1. **Search before writing** — Use `gitnexus_query` to find existing code. Check sibling files. Reuse Lua scripts. Extract shared logic instead of duplicating.
2. **Impact before edit** — Run `gitnexus_impact` on every symbol you touch. Warn on HIGH/CRITICAL.
3. **Test the change** — Targeted tests only. Run pint and phpstan on edited paths.
4. **Verify scope** — `gitnexus_detect_changes` before commit.
5. **Document** — Update CHANGELOG.md with what, why, and blast radius.
6. **Redis atomicity** — Any multi-key read-then-write MUST be a single Lua script. Pipelines are NOT atomic.
7. **Redis prefix** — `rawCommand`/`Redis::command` bypass OPT_PREFIX. Always prepend `config('database.redis.options.prefix', '')`.
8. **Fail closed** — Redis errors in security paths (rate limiting, cache invalidation) must reject, never allow.
9. **Simulation harness** — `php artisan simulation:run` is a strict HTTP consumer. Verify via **both** web and API v1 surfaces, diffing derived DB state.
