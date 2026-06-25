# Orphaned Code Detection Design

> **Context:** Sweep CEMS-MY Laravel 10 banking system for orphaned/detached code across all layers.
> **Approach:** Hybrid (GitNexus graph analysis + custom scripts for layers GitNexus can't index well).

## Scope

| Layer | What we check | Detection tool |
|---|---|---|
| PHP Classes & Methods | Services, Controllers, Jobs, Listeners, Commands, Repositories, DTOs, Helpers with zero callers | GitNexus impact (upstream) + Cypher |
| Routes | Route defs with no matching controller method; controller methods with no route binding; routes with zero consumers | GitNexus route_map |
| Blade Views | `.blade.php` files never referenced by `view()`, `@include`, `@extends`, `@each`, or `Mail::send()` | Custom PHP script |
| Database Schema | Tables/columns defined in schema but not referenced in app code, config, routes, or views | Custom script (grep-based) |
| Enums & Config | Enum cases and config keys never used in application code | GitNexus context + grep |
| JS/CSS Assets | Frontend files not reachable from any entry point | Custom script |

**Excluded:** vendor/, node_modules/, storage/, tests/ (unless they test orphaned code only)

## Detection Methodology

### 1. PHP Classes & Methods

For each `.php` file in `app/` (excluding Models — they have dynamic usage via queries):
- Run `gitnexus_impact(target=<ClassName>, direction=upstream, includeTests=false)`.
- Flag classes/methods with zero callers (excluding abstract methods, interfaces).
- Cypher query for bulk detection:
  ```cypher
  MATCH (c:Class|Method) WHERE NOT (c)<-[:CodeRelation {type: 'CALLS'}]-()
  AND NOT c.name CONTAINS 'Controller'
  RETURN c.name, c.filePath, c.kind
  ```
- Confidence: HIGH for graph-exact matches, MEDIUM for text-search only.

### 2. Routes

- Use `gitnexus_route_map()` to list all routes, handlers, and consumers.
- Flag:
  - Routes with handlers pointing to non-existent controller methods
  - Routes with zero frontend consumers
  - Controller methods referenced in `routes/*.php` but deleted from the controller class
- Report broken routes as MEDIUM risk (would 404).

### 3. Blade Views

Custom PHP script `scripts/find-orphaned-views.php`:
1. Scan all views in `resources/views/` — build master list of `.blade.php` paths.
2. Extract references from:
   - `view()` calls in PHP files
   - `View::make()` calls
   - `@include`, `@extends`, `@each`, `@component`, `@includeIf` in Blade files
   - `Mail::send()` / `Notification::send()` view references
   - `config('view.paths')` references
   - Layout names from config
3. Cross-reference. Unmatched views = orphaned.
4. Handle `::` namespace syntax (vendor views), dot-notation (`pages.dashboard`).
5. Confidence: HIGH for exact match, MEDIUM for namespace match.

### 4. Database Schema

Custom PHP script `scripts/find-orphaned-db.php`:
1. Query information_schema for all tables and columns.
2. For each table/column name, grep across `app/`, `config/`, `routes/`, `resources/views/`, `database/`.
3. Common column names (e.g., `id`, `name`, `created_at`) are filtered out (too many false positives).
4. Flag tables/columns with zero code references.

### 5. Enums & Config

- Use `gitnexus_query()` or `gitnexus_context()` on each Enum class to trace usage.
- For config keys, grep for `config('key')` callers against known keys from `config/*.php`.
- Flag unused enum cases and config keys.

### 6. JS/CSS Assets

- Parse entry points in `resources/js/` (usually `app.js` or Vite config).
- Trace import/require dependencies.
- List files not reachable from any entry point.
- Same for CSS files in `resources/css/`.

## Report Format

Written to `docs/orphaned-code-report.md`. Each finding:

```
## Layer: PHP Classes & Methods

### `app/Services/OldService.php`
- **Orphaned:** `calculateRate()` — zero callers
- **Confidence:** High (GitNexus graph, exact match)
- **Risk:** Low (no consumers)
- **Suggested action:** Remove
```

Confidence: High (graph/compiler) / Medium (text-search) / Low (heuristic)

Risk: Low (safe to delete) / Medium (broken runtime) / High (could affect unknown consumers)

## Action Plan After Report

Each finding tagged as:
- **SAFE TO DELETE** — no dependencies anywhere
- **NEEDS REVIEW** — ambiguous references (commented code, config refs)
- **BROKEN ROUTE** — route points to non-existent handler (fix urgently)