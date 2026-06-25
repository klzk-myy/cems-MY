# Orphaned Code Detection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Identify all orphaned/detached code across PHP, routes, Blade views, DB schema, enums/config, and frontend assets in the CEMS-MY codebase.

**Architecture:** Hybrid approach — GitNexus knowledge graph for PHP/routes/imports (high-confidence), custom PHP scripts for Blade views and DB schema (full coverage). All findings compiled into a single categorized Markdown report.

**Tech Stack:** GitNexus MCP tools, PHP 8.3 CLI scripts, bash/grep, Vite asset scanning

---

### Task 1: Re-index GitNexus (ensure fresh state)

**Files:**
- Run: `npx gitnexus analyze` in project root

- [ ] **Step 1: Run GitNexus analysis to refresh index**

  ```bash
  npx gitnexus analyze
  ```

  Expected: Index completes without errors

- [ ] **Step 2: Verify index health**

  Run: `gitnexus_list_repos()` and confirm repo is listed with recent indexed date.

---

### Task 2: PHP Classes & Methods — Bulk Cypher Query for Zero-Caller Classes

**Files:** None (read-only GitNexus query)

- [ ] **Step 1: Query all classes with zero incoming CALLS edges**

  Run:
  ```cypher
  MATCH (c:Class) WHERE NOT (c)<-[:CodeRelation {type: 'CALLS'}]-()
  AND c.filePath STARTS WITH 'app/'
  RETURN c.name, c.filePath, c.kind
  ORDER BY c.filePath
  ```

  Expected: Returns list of classes in `app/` with no callers.
  Record results in temporary file `/tmp/orphans-php-classes.txt`.

- [ ] **Step 2: Query all methods (non-abstract, non-interface) with zero callers**

  Run:
  ```cypher
  MATCH (m:Method) WHERE NOT (m)<-[:CodeRelation {type: 'CALLS'}]-()
  AND m.filePath STARTS WITH 'app/'
  AND m.visibility <> 'private'
  RETURN m.name, m.filePath, m.visibility
  ORDER BY m.filePath
  ```

  Expected: Returns list of methods in `app/` with no callers.
  Record in `/tmp/orphans-php-methods.txt`.

- [ ] **Step 3: Filter out false positives**

  Common false positives to ignore:
  - Methods on Eloquent models (accessed dynamically via queries)
  - `boot()`, `register()` on ServiceProviders
  - `handle()` on Jobs/Listeners (dispatched dynamically)
  - `__invoke()`, `__construct()` on any class
  - Methods named `rules()`, `authorize()` on FormRequests
  - Blade `@include`d view composers (registered dynamically)

  Create `/tmp/orphans-php-candidates.txt` with remaining entries.

---

### Task 3: PHP Classes & Methods — Impact Analysis on Top-Level Services

**Files:** None (read-only GitNexus tools)

- [ ] **Step 1: Run impact analysis on each service class in `app/Services/`**

  Iterate over all top-level service files (excluding subdirectories like Contracts/, Compliance/):
  ```bash
  for f in app/Services/*.php; do
      class=$(basename "$f" .php)
      echo "=== $class ==="
      # Run impact analysis via the tool (manual per call)
      # gitnexus_impact(target="$class", direction="upstream", includeTests=false)
  done
  ```

  For each, check the summary:
  - `d=1 items: 0` means zero direct callers → flag as orphaned candidate
  - Append result to `/tmp/orphans-php-candidates.txt`

  Expected: Each service returns its dependency chain. Flag services with no callers.

- [ ] **Step 2: Run impact analysis on Controllers**

  Same approach — iterate over all controller files:
  ```bash
  find app/Http/Controllers -name "*Controller.php" | while read -r f; do
      class=$(basename "$f" .php)
      namespace=$(php -r "echo (new ReflectionClass('App\Http\Controllers\\$class'))->isInstantiable() ? '1' : '0';" 2>/dev/null || echo "0")
      [ "$namespace" = "1" ] && echo "=== $class ==="
  done
  ```

  For each controller, run `gitnexus_impact(target="$class", direction="upstream")`.
  A controller with zero incoming references (no routes pointing to it) is orphaned.

- [ ] **Step 3: Run impact analysis on Jobs and Commands**

  ```bash
  gitnexus_impact(target="SomeJob", direction="upstream", includeTests=false)
  gitnexus_impact(target="SomeCommand", direction="upstream", includeTests=false)
  ```

  Jobs dispatched via `dispatch()` may not show CALLS edges — cross-reference with `dispatch()` / `dispatchNow()` grep results.

  Record candidates in `/tmp/orphans-php-candidates.txt`.

---

### Task 4: Routes — Map All Routes and Their Consumers

**Files:** None (read-only GitNexus tools)

- [ ] **Step 1: Get complete route map**

  Run: `gitnexus_route_map()` (no filter — all routes)

  Expected: Returns every route with handler and consumer info. Record to `/tmp/routes-map.txt`.

- [ ] **Step 2: Check route handlers against existing controller methods**

  For each route with a `Controller@method` handler:
  1. Read the controller file
  2. Check that the method exists

  Flag routes where:
  - Handler method doesn't exist → **BROKEN ROUTE**
  - Route has zero consumers (no frontend file fetches it) → **ORPHANED ROUTE**

  Record in `/tmp/orphans-routes.txt`.

- [ ] **Step 3: Cross-reference with live route list**

  Run: `php artisan route:list --compact` to get registered routes.
  Cross-check against route definitions in `routes/*.php`.

  Flag route definitions in files that aren't actually registered (e.g., missing `Route::group()`).

---

### Task 5: Blade Views — Create the scan script

**Files:**
- Create: `scripts/find-orphaned-views.php`
- Run: `php scripts/find-orphaned-views.php`

- [ ] **Step 1: Write the scan script**

  ```php
  <?php

  /**
   * Find orphaned Blade view files — views never referenced by view(), @include, @extends, etc.
   *
   * Usage: php scripts/find-orphaned-views.php
   * Output: JSON lines to stdout, summary to stderr
   */

  $viewsDir = __DIR__ . '/../resources/views';

  // 1. Collect all .blade.php files
  $allViews = [];
  $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($viewsDir, RecursiveDirectoryIterator::SKIP_DOTS)
  );
  foreach ($iterator as $file) {
      if ($file->getExtension() === 'php' && str_contains($file->getFilename(), '.blade.')) {
          $relativePath = str_replace($viewsDir . '/', '', $file->getPathname());
          $dotName = str_replace('/', '.', str_replace('.blade.php', '', $relativePath));
          $allViews[$dotName] = $relativePath;
      }
  }

  // 2. Collect all view references from app code
  $appDirs = [__DIR__ . '/../app', __DIR__ . '/../resources/views', __DIR__ . '/../routes', __DIR__ . '/../config', __DIR__ . '/../database'];

  $referenced = [];

  foreach ($appDirs as $dir) {
      $files = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
      );
      foreach ($files as $file) {
          if (!in_array($file->getExtension(), ['php', 'blade.php'])) continue;
          $content = file_get_contents($file->getPathname());

          // Match view('dot.name'), view('namespace::dot.name')
          preg_match_all('/view\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
          foreach ($matches[1] as $v) $referenced[] = $v;

          // Match @include('dot.name'), @extends('dot.name'), @includeIf, @each
          preg_match_all('/@(?:include|extends|includeIf|each|component)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
          foreach ($matches[1] as $v) $referenced[] = $v;

          // Match Mail::send('dot.name'), Notification::send(..., 'dot.name')
          preg_match_all('/Mail::send\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
          foreach ($matches[1] as $v) $referenced[] = $v;

          // Match View::make('dot.name')
          preg_match_all('/View::make\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
          foreach ($matches[1] as $v) $referenced[] = $v;

          // Match Layout::render('dot.name')
          preg_match_all('/render\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
          foreach ($matches[1] as $v) $referenced[] = $v;
      }
  }

  $referenced = array_unique($referenced);

  // 3. Remove namespace prefix (e.g., 'admin::pages.dashboard' -> 'pages.dashboard')
  $cleanReferenced = [];
  foreach ($referenced as $ref) {
      if (str_contains($ref, '::')) {
          $cleanReferenced[] = explode('::', $ref, 2)[1];
      } else {
          $cleanReferenced[] = $ref;
      }
  }
  $cleanReferenced = array_unique($cleanReferenced);

  // 4. Cross-reference
  $orphaned = [];
  foreach ($allViews as $dotName => $path) {
      if (!in_array($dotName, $cleanReferenced)) {
          // Check namespaced references
          $found = false;
          foreach ($cleanReferenced as $ref) {
              if (str_ends_with($ref, '.' . $dotName) || $ref === $dotName) {
                  $found = true;
                  break;
              }
          }
          if (!$found) {
              $orphaned[] = [
                  'view' => $dotName,
                  'path' => $path,
                  'confidence' => 'HIGH',
              ];
          }
      }
  }

  // 5. Output
  echo json_encode(['total_views' => count($allViews), 'referenced' => count($cleanReferenced), 'orphaned' => count($orphaned)], JSON_PRETTY_PRINT) . "\n";
  foreach ($orphaned as $o) {
      echo json_encode($o) . "\n";
  }

  fwrite(STDERR, "Total views: " . count($allViews) . "\n");
  fwrite(STDERR, "Referenced: " . count($cleanReferenced) . "\n");
  fwrite(STDERR, "Orphaned candidates: " . count($orphaned) . "\n");
  ```

  Write this script to `scripts/find-orphaned-views.php`.

- [ ] **Step 2: Run the script**

  ```bash
  php scripts/find-orphaned-views.php 2>&1 | tee /tmp/orphans-views-output.txt
  ```

  Expected: Shows total views, referenced count, orphaned count. Orphaned views listed in JSON lines.

- [ ] **Step 3: Extract orphaned views list**

  ```bash
  php scripts/find-orphaned-views.php 2>/dev/null | tail -n +2 > /tmp/orphans-views.txt
  ```

  Expected: `/tmp/orphans-views.txt` contains one JSON line per orphaned view.

---

### Task 6: Database Schema — Create the scan script

**Files:**
- Create: `scripts/find-orphaned-db.php`
- Run: `php scripts/find-orphaned-db.php`

- [ ] **Step 1: Write the DB schema scan script**

  ```php
  <?php

  /**
   * Find orphaned database tables and columns.
   *
   * Scans information_schema for all tables/columns, then greps across app code
   * to see if they're referenced.
   *
   * Usage: php scripts/find-orphaned-db.php
   * Output: JSON to stdout
   */

  // Read .env for DB credentials
  $env = parse_ini_file(__DIR__ . '/../.env');
  $dbName = $env['DB_DATABASE'] ?? null;
  $host = $env['DB_HOST'] ?? '127.0.0.1';
  $user = $env['DB_USERNAME'] ?? 'root';
  $pass = $env['DB_PASSWORD'] ?? '';

  if (!$dbName) {
      fwrite(STDERR, "ERROR: DB_DATABASE not found in .env\n");
      exit(1);
  }

  // Query information_schema
  try {
      $pdo = new PDO("mysql:host=$host;dbname=information_schema;charset=utf8mb4", $user, $pass);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      $stmt = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION");
      $stmt->execute([$dbName]);
      $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
      fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
      exit(1);
  }

  // Group by table
  $schema = [];
  foreach ($columns as $col) {
      $schema[$col['TABLE_NAME']][] = $col['COLUMN_NAME'];
  }

  // Common column names to skip (too many false positives)
  $commonColumns = [
      'id', 'name', 'created_at', 'updated_at', 'deleted_at',
      'code', 'description', 'status', 'type', 'active',
      'notes', 'reference', 'date', 'amount', 'branch_id',
      'user_id', 'created_by', 'updated_by', 'is_active',
  ];

  // Search dirs
  $searchDirs = [
      __DIR__ . '/../app',
      __DIR__ . '/../config',
      __DIR__ . '/../routes',
      __DIR__ . '/../resources/views',
      __DIR__ . '/../database',
  ];

  $orphanedTables = [];
  $orphanedColumns = [];

  foreach ($schema as $table => $cols) {
      // Skip migration, cache, session tables
      if (str_contains($table, 'migrations') || $table === 'cache' || $table === 'sessions' || $table === 'failed_jobs') {
          continue;
      }

      // Check if table is referenced anywhere in the codebase
      $tableRefs = 0;
      $tableSnake = str_replace('_', '_', $table);
      foreach ($searchDirs as $dir) {
          $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
          foreach ($files as $file) {
              if (!in_array($file->getExtension(), ['php', 'blade.php'])) continue;
              $content = file_get_contents($file->getPathname());
              // Match model references: table name in model class, Schema::table, DB::table, etc.
              if (preg_match('/' . preg_quote($tableSnake, '/') . '/', $content)) {
                  $tableRefs++;
                  break 2; // Found in this dir, move to next table
              }
              // Also match StudlyCase model name
              $studly = implode('', array_map('ucfirst', explode('_', $table)));
              if (substr($studly, -1) === 's') $studly = substr($studly, 0, -1);
              if (preg_match('/' . preg_quote($studly, '/') . '/', $content)) {
                  $tableRefs++;
                  break 2;
              }
          }
      }

      if ($tableRefs === 0) {
          $orphanedTables[] = $table;
          continue;
      }

      // Check individual columns (only for non-orphaned tables)
      foreach ($cols as $col) {
          if (in_array($col, $commonColumns)) continue;

          $colRefs = 0;
          foreach ($searchDirs as $dir) {
              $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
              foreach ($files as $file) {
                  if (!in_array($file->getExtension(), ['php', 'blade.php'])) continue;
                  $content = file_get_contents($file->getPathname());
                  if (preg_match('/' . preg_quote($col, '/') . '/', $content)) {
                      $colRefs++;
                      break 2;
                  }
              }
          }

          if ($colRefs === 0) {
              $orphanedColumns[] = ['table' => $table, 'column' => $col];
          }
      }
  }

  // Output
  $result = [
      'total_tables' => count($schema),
      'orphaned_tables' => $orphanedTables,
      'orphaned_columns' => $orphanedColumns,
  ];

  echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

  fwrite(STDERR, "Total tables: " . count($schema) . "\n");
  fwrite(STDERR, "Orphaned tables: " . count($orphanedTables) . "\n");
  fwrite(STDERR, "Orphaned columns: " . count($orphanedColumns) . "\n");
  ```

  Write this script to `scripts/find-orphaned-db.php`.

- [ ] **Step 2: Run the DB scan**

  ```bash
  php scripts/find-orphaned-db.php 2>&1 | tee /tmp/orphans-db-output.txt
  ```

  Expected: Shows table/column counts and orphaned items.

- [ ] **Step 3: Extract orphaned items**

  ```bash
  grep -o '"orphaned_tables": \[.*\]' /tmp/orphans-db-output.txt | head -1 > /tmp/orphans-db-tables.txt
  grep -o '"orphaned_columns": \[.*\]' /tmp/orphans-db-output.txt | head -1 > /tmp/orphans-db-columns.txt
  ```

---

### Task 7: Enums — Trace Usage via GitNexus Context

**Files:** None (read-only GitNexus tools)

- [ ] **Step 1: List all enum classes**

  ```bash
  ls app/Enums/*.php | xargs -n1 basename | sed 's/\.php//'
  ```

  Record list to `/tmp/enum-list.txt`.

- [ ] **Step 2: For each enum, trace context**

  Iterate over all enum files:
  ```bash
  for f in app/Enums/*.php; do
      enum=$(basename "$f" .php)
      echo "=== $enum ==="
      # gitnexus_context(name="$enum", kind="Enum", include_content=false)
  done
  ```

  Check the result: if the enum has zero incoming references, flag it.

  Record orphaned enums to `/tmp/orphans-enums.txt`.

- [ ] **Step 3: Check individual enum cases**

  For enums that ARE used, check if specific cases are unreferenced. Grep for each case value:
  ```bash
  case_value="PENDING_APPROVAL"
  rg -r '' "$case_value" app/ resources/ routes/ config/ --include '*.php' -l | wc -l
  ```

  Record unused cases to `/tmp/orphans-enum-cases.txt`.

---

### Task 8: Config Keys — Scan for Unused Keys

**Files:** None (read-only grep)

- [ ] **Step 1: Extract all config keys from config/*.php**

  For each config file, extract the top-level keys:
  ```bash
  for f in config/*.php; do
      filename=$(basename "$f" .php)
      echo "=== $filename ==="
      php -r "
          \$config = require '$f';
          foreach (\$config as \$key => \$val) {
              echo '$filename.' . \$key . '\n';
          }
      "
  done > /tmp/all-config-keys.txt
  ```

- [ ] **Step 2: Check each config key for usage**

  ```bash
  grep -v '^===' /tmp/all-config-keys.txt | while IFS= read -r key; do
      matches=$(rg -r '' "config\(['\"]$key" app/ resources/ routes/ --include '*.php' -l | wc -l)
      if [ "$matches" -eq 0 ]; then
          echo "UNUSED: $key"
      fi
  done > /tmp/orphans-config-keys.txt
  ```

  Expected: Outputs config keys with zero code references.

- [ ] **Step 3: Filter out known defaults**

  Remove config keys that are only used in `config/*.php` itself (they're read by Laravel framework internally — `app.name`, `app.env`, `database.default`, etc.).

  Final list in `/tmp/orphans-config-candidates.txt`.

---

### Task 9: JS/CSS Assets — Check for Unreachable Files

**Files:**
- Create: `scripts/find-orphaned-assets.sh`

- [ ] **Step 1: Write the asset scan script**

  ```bash
  cat > scripts/find-orphaned-assets.sh << 'SCRIPT'
  #!/bin/bash
  # Find JS/CSS files not imported by any entry point

  JS_DIR="resources/js"
  CSS_DIR="resources/css"

  echo "=== JavaScript Files ==="

  # Find all JS files
  find "$JS_DIR" -name "*.js" -type f | while read -r file; do
      rel_path="${file#$JS_DIR/}"

      # Skip entry point itself
      [ "$rel_path" = "app.js" ] && continue
      [ "$rel_path" = "bootstrap.js" ] && continue

      # Check if imported anywhere
      if ! rg -r '' -g '*.{js,vue}' "$rel_path" resources/js/ > /dev/null 2>&1; then
          # Also check for path without extension
          no_ext="${rel_path%.js}"
          if ! rg -r '' -g '*.{js,vue}' "$no_ext" resources/js/ > /dev/null 2>&1; then
              echo "UNUSED: $rel_path"
          fi
      fi
  done

  echo ""
  echo "=== CSS Files ==="

  find "$CSS_DIR" -name "*.css" -type f | while read -r file; do
      rel_path="${file#$CSS_DIR/}"

      # Skip entry point
      [ "$rel_path" = "app.css" ] && continue

      # Check if imported anywhere
      if ! rg -r '' -g '*.{css,js,vue}' "$rel_path" resources/ > /dev/null 2>&1; then
          echo "UNUSED: $rel_path"
      fi
  done
  SCRIPT
  chmod +x scripts/find-orphaned-assets.sh
  ```

- [ ] **Step 2: Run the asset scan**

  ```bash
  bash scripts/find-orphaned-assets.sh 2>&1 | tee /tmp/orphans-assets.txt
  ```

  Expected: Lists JS/CSS files not reachable from any entry point.

---

### Task 10: Compile Final Report

**Files:**
- Create: `docs/orphaned-code-report.md`

- [ ] **Step 1: Merge all findings into the report**

  Create the report document:

  ```bash
  cat > docs/orphaned-code-report.md << 'REPORT'
  # Orphaned Code Detection Report

  > Generated: 2026-06-02
  > Method: Hybrid (GitNexus + custom scripts)

  ## Summary

  | Layer | Candidates | Confidence |
  |-------|-----------|------------|
  | PHP Classes & Methods | (from Task 2-3) | High |
  | Routes | (from Task 4) | High |
  | Blade Views | (from Task 5) | High |
  | Database Schema | (from Task 6) | Medium |
  | Enums & Config | (from Task 7-8) | High/Medium |
  | Frontend Assets | (from Task 9) | Medium |

  ---
  REPORT

  echo "" >> docs/orphaned-code-report.md
  echo "## 1. PHP Classes & Methods" >> docs/orphaned-code-report.md
  echo "" >> docs/orphaned-code-report.md
  ```

  Then append each layer's findings in the format agreed in the design doc.

- [ ] **Step 2: Append PHP findings**

  Format each candidate from `/tmp/orphans-php-candidates.txt` into the report:

  ```bash
  cat /tmp/orphans-php-candidates.txt | while IFS='|' read -r name filepath confidence; do
      cat >> docs/orphaned-code-report.md << EOF
  ### \`$filepath\`
  - **Orphaned:** \`$name\` — zero callers
  - **Confidence:** $confidence (GitNexus graph)
  - **Risk:** Low
  - **Suggested action:** Remove

  EOF
  done
  ```

- [ ] **Step 3: Append route findings**

  Same format for each broken/orphaned route from `/tmp/orphans-routes.txt`.

- [ ] **Step 4: Append view findings**

  Same format for each orphaned view from `/tmp/orphans-views.txt`.

- [ ] **Step 5: Append DB findings**

  Same format for each orphaned table/column from `/tmp/orphans-db-output.txt`.

- [ ] **Step 6: Append enum/config/asset findings**

  Same format from `/tmp/orphans-enums.txt`, `/tmp/orphans-config-candidates.txt`, `/tmp/orphans-assets.txt`.

- [ ] **Step 7: Add action plan section**

  ```bash
  cat >> docs/orphaned-code-report.md << 'EOF'
  ## Action Plan

  Each finding tagged as:
  - **SAFE TO DELETE** — no dependencies anywhere
  - **NEEDS REVIEW** — ambiguous references exist
  - **BROKEN ROUTE** — route points to non-existent handler (fix urgently)

  ### Next Steps
  1. Review SAFE TO DELETE items — remove in a cleanup PR
  2. Review NEEDS REVIEW items — investigate each ambiguity
  3. Fix BROKEN ROUTE items — update handler references or remove routes
  EOF
  ```

- [ ] **Step 8: Commit the report**

  ```bash
  git add docs/orphaned-code-report.md
  git commit -m "docs: add orphaned code detection report"
  ```

---

### Task 11: Present Report for User Review

**Files:** `docs/orphaned-code-report.md`

- [ ] **Step 1: Present the report summary**

  Show the user the summary table from the report with counts per layer.

  Ask: "Report is ready at `docs/orphaned-code-report.md`. Would you like to:
  1. Review and take action on findings now
  2. Let me create a cleanup implementation plan based on the findings"