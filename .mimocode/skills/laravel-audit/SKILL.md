---
name: laravel-audit
description: "Use when the user asks to analyze, audit, or review a Laravel project. Performs a comprehensive end-to-end codebase analysis using GitNexus and parallel subagent exploration. Examples: 'analyze this project', 'code review', 'audit the codebase', 'what's the architecture?'"
---

# Laravel Project Audit

Systematically analyze a Laravel project's architecture, code quality, and structure. Combines GitNexus code intelligence with parallel subagent exploration for comprehensive coverage.

## When to Use

- "Analyze this project"
- "Perform a code review"
- "Audit the codebase"
- "What's the architecture?"
- Onboarding to an unfamiliar Laravel project
- Periodic code quality reviews

## Prerequisites

- GitNexus index must be up to date (check with `npx gitnexus status`)
- If stale, run `npx gitnexus analyze` first

## Workflow

### Phase 1: Project Context (Sequential)

```
1. Load gitnexus-cli skill → check index status
2. READ gitnexus://repo/{name}/context → codebase overview
3. READ README.md, composer.json → understand dependencies and purpose
4. READ routes/web.php, routes/api.php (or api_v1.php, api_v2.php) → map endpoints
```

### Phase 2: Parallel Exploration (Subagents)

Spawn 4 explore subagents in parallel for maximum efficiency:

```
Subagent 1 — Models & Data Layer:
  - List all app/Models/*.php files
  - Read 3-5 key models (largest/most complex)
  - Check app/Models/Bases/ for base classes
  - List database/migrations/ to understand schema
  - Check app/Enums/ for type-safe attributes

Subagent 2 — Services & Business Logic:
  - List all app/Services/*.php files
  - Read 3-5 key services
  - Check app/Services/*/ for subdirectories
  - Identify service patterns (repositories, strategies, etc.)

Subagent 3 — Controllers & HTTP Layer:
  - List all app/Http/Controllers/**/*.php files
  - Read 3-5 key controllers
  - List app/Http/Middleware/ and read key middleware
  - Check app/Http/Requests/ for Form Requests
  - Check app/Http/Resources/ for API Resources

Subagent 4 — Tests & Quality:
  - List all tests/**/*.php files
  - Read test base classes (TestCase.php, CreatesApplication.php)
  - Check tests/Feature/, tests/Unit/, tests/Load/ structure
  - Identify test coverage patterns
```

### Phase 3: Deep Dive (Sequential)

After subagents return, read specific files based on findings:

```
- Key enums in app/Enums/
- Base model classes in app/Models/Bases/
- Custom middleware
- Route definitions
- Config files relevant to architecture
```

### Phase 4: Compilation

Produce a structured audit report covering:

1. **Project Overview** — purpose, tech stack, Laravel version
2. **Architecture** — directory structure, design patterns used
3. **Data Layer** — models, relationships, enums, migrations
4. **Business Logic** — services, jobs, event listeners
5. **HTTP Layer** — controllers, middleware, routes, API structure
6. **Testing** — test structure, coverage patterns, gaps
7. **Code Quality** — notable patterns, potential issues, recommendations

## Checklist

```
- [ ] GitNexus index is current
- [ ] Read project context and dependencies
- [ ] Map all routes and endpoints
- [ ] Explore models and data layer (subagent)
- [ ] Explore services and business logic (subagent)
- [ ] Explore controllers and HTTP layer (subagent)
- [ ] Explore tests and quality (subagent)
- [ ] Deep dive on key files
- [ ] Compile audit report
```

## Tips

- Use `glob` patterns like `app/Models/*.php`, `app/Services/*.php`, `app/Http/Controllers/**/*.php` for fast directory scanning
- Spawn subagents with `actor` tool for parallel exploration — this is significantly faster than sequential reads
- For Laravel 10, check `app/Http/Kernel.php` for middleware, `app/Console/Kernel.php` for commands, `app/Exceptions/Handler.php` for error handling
- For Laravel 11+, check `bootstrap/app.php` for application configuration
- Always check `composer.json` for package versions and `config/` for custom configuration
