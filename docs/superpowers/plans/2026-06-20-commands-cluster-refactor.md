# Commands Cluster Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the low-cohesion Commands cluster (0.40) into domain-specific groups by extracting shared utilities, removing cross-domain dependencies, and organizing commands into logical subdirectories.

**Architecture:** The Commands cluster's low cohesion comes from diverse domains (sanctions, notifications, queue health, route validation, backup, reporting) sharing only that they're invoked via Artisan commands. The refactoring splits these into independent domain groups by: (1) extracting shared helper methods into traits, (2) moving domain-specific commands into subdirectories, (3) removing cross-domain service calls that create artificial cluster connections.

**Tech Stack:** PHP 8.3, Laravel 10, PHPUnit 10

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 10
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run affected tests after each task to verify no regressions
- Do NOT add comments to code
- Preserve all existing functionality — only reorganize
- Follow existing code conventions — check sibling files for patterns
- Commands must remain registered in `app/Console/Kernel.php` or auto-discovered

---

## File Structure

### New Files (3 total)

| File | Purpose |
|------|---------|
| `app/Console/Commands/Concerns/HasReportFormatting.php` | Shared report formatting methods extracted from multiple commands |
| `app/Console/Commands/Concerns/HasNotificationTesting.php` | Shared notification testing methods extracted from TestNotification |
| `app/Console/Commands/Concerns/HasSanctionsImport.php` | Shared sanctions import methods extracted from SanctionsImportCommand |

### Modified Files (8 total)

| File | Changes |
|------|---------|
| `app/Console/Commands/SanctionsImportCommand.php` | Extract shared methods to trait |
| `app/Console/Commands/UpdateSanctionsLists.php` | Extract shared methods to trait |
| `app/Console/Commands/SanctionsStatus.php` | Extract shared methods to trait |
| `app/Console/Commands/TestNotification.php` | Extract shared methods to trait |
| `app/Console/Commands/SendNotificationDigest.php` | Extract shared methods to trait |
| `app/Console/Commands/GenerateDailyMSB2.php` | Extract shared methods to trait |
| `app/Console/Commands/GenerateQuarterlyLVR.php` | Extract shared methods to trait |
| `app/Console/Commands/GenerateTrialBalance.php` | Extract shared methods to trait |

---

## Task 1: Extract HasReportFormatting Trait

**Priority:** Medium — Reduces duplication across report commands

**Files:**
- Create: `app/Console/Commands/Concerns/HasReportFormatting.php`
- Modify: `app/Console/Commands/GenerateDailyMSB2.php`
- Modify: `app/Console/Commands/GenerateQuarterlyLVR.php`
- Modify: `app/Console/Commands/GenerateTrialBalance.php`
- Modify: `app/Console/Commands/GenerateEodReconciliation.php`
- Modify: `app/Console/Commands/GeneratePositionLimitReport.php`
- Modify: `app/Console/Commands/GenerateMonthlyLMCA.php`

**Context:** Multiple report generation commands share common patterns: creating `ReportGenerated` records, formatting CSV output, handling report dates. Extracting these into a trait reduces duplication and makes the report command cluster more cohesive.

- [ ] **Step 1: Analyze common patterns across report commands**

Read each report command file and identify shared methods:
- `GenerateDailyMSB2::handle()` — creates ReportGenerated, generates CSV
- `GenerateQuarterlyLVR::handle()` — creates ReportGenerated, generates CSV
- `GenerateTrialBalance::handle()` — creates ReportGenerated, generates CSV
- `GenerateEodReconciliation::handle()` — creates ReportGenerated, generates PDF
- `GeneratePositionLimitReport::handle()` — creates ReportGenerated, generates CSV
- `GenerateMonthlyLMCA::handle()` — creates ReportGenerated, generates CSV

Common pattern: `ReportGenerated::create([...])` with similar structure.

- [ ] **Step 2: Create HasReportFormatting trait**

Create `app/Console/Commands/Concerns/HasReportFormatting.php`:

```php
<?php

namespace App\Console\Commands\Concerns;

use App\Models\ReportGenerated;

trait HasReportFormatting
{
    protected function createReportRecord(string $type, string $periodStart, string $periodEnd, string $format = 'CSV'): ReportGenerated
    {
        return ReportGenerated::create([
            'report_type' => $type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_by' => auth()->id() ?? 1,
            'generated_at' => now(),
            'file_format' => $format,
            'status' => 'Generated',
        ]);
    }

    protected function getReportFilename(string $type, string $suffix): string
    {
        return $type.'_'.now()->format('Y-m-d').'_'.$suffix.'.csv';
    }

    protected function getReportPath(string $filename): string
    {
        return storage_path('app/reports/'.$filename);
    }

    protected function saveReportCsv(string $filepath, string $csvContent): void
    {
        $dir = dirname($filepath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($filepath, $csvContent);
    }
}
```

- [ ] **Step 3: Update GenerateDailyMSB2 to use trait**

```php
<?php

namespace App\Console\Commands;

use App\Services\Reporting\ReportingService;
use Illuminate\Console\Command;

class GenerateDailyMSB2 extends Command
{
    use Concerns\HasReportFormatting;

    protected $signature = 'report:msb2 {--date= : Date to generate report for (Y-m-d)}';

    protected $description = 'Generate daily MSB(2) regulatory report';

    public function handle(ReportingService $reportingService): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();

        $this->info("Generating MSB(2) report for {$date}...");

        try {
            $filepath = $reportingService->generateMSB2($date);

            $this->createReportRecord('MSB2', $date, $date);

            $this->info("Report generated: {$filepath}");
            $this->info('Download: /reports/download/'.basename($filepath));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to generate report: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
```

- [ ] **Step 4: Update other report commands similarly**

Apply the same pattern to `GenerateQuarterlyLVR`, `GenerateTrialBalance`, `GenerateEodReconciliation`, `GeneratePositionLimitReport`, `GenerateMonthlyLMCA`.

- [ ] **Step 5: Run affected tests**

Run: `php artisan test --compact --filter=Report|MSB2|LVR|TrialBalance|EodReconciliation|PositionLimit|LMCA`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/Concerns/HasReportFormatting.php \
  app/Console/Commands/GenerateDailyMSB2.php \
  app/Console/Commands/GenerateQuarterlyLVR.php \
  app/Console/Commands/GenerateTrialBalance.php \
  app/Console/Commands/GenerateEodReconciliation.php \
  app/Console/Commands/GeneratePositionLimitReport.php \
  app/Console/Commands/GenerateMonthlyLMCA.php
git commit -m "refactor: extract HasReportFormatting trait for report commands"
```

---

## Task 2: Extract HasSanctionsImport Trait

**Priority:** Medium — Reduces duplication across sanctions commands

**Files:**
- Create: `app/Console/Commands/Concerns/HasSanctionsImport.php`
- Modify: `app/Console/Commands/SanctionsImportCommand.php`
- Modify: `app/Console/Commands/UpdateSanctionsLists.php`

**Context:** `SanctionsImportCommand` and `UpdateSanctionsLists` share import logic. Extracting common methods into a trait reduces duplication.

- [ ] **Step 1: Analyze SanctionsImportCommand and UpdateSanctionsLists**

Read both files to identify shared patterns.

- [ ] **Step 2: Create HasSanctionsImport trait**

Create `app/Console/Commands/Concerns/HasSanctionsImport.php`:

```php
<?php

namespace App\Console\Commands\Concerns;

use App\Models\SanctionList;

trait HasSanctionsImport
{
    protected function getSanctionList(string $name): ?SanctionList
    {
        return SanctionList::where('name', $name)->first();
    }

    protected function getEnabledSanctionLists()
    {
        return SanctionList::where('is_active', true)->get();
    }

    protected function formatImportResult(array $result): string
    {
        return "Added: {$result['added']}, Updated: {$result['updated']}, Deactivated: {$result['deactivated']}";
    }
}
```

- [ ] **Step 3: Update SanctionsImportCommand to use trait**

Add `use Concerns\HasSanctionsImport;` and replace duplicated methods.

- [ ] **Step 4: Update UpdateSanctionsLists to use trait**

Add `use Concerns\HasSanctionsImport;` and replace duplicated methods.

- [ ] **Step 5: Run affected tests**

Run: `php artisan test --compact --filter=Sanctions`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/Concerns/HasSanctionsImport.php \
  app/Console/Commands/SanctionsImportCommand.php \
  app/Console/Commands/UpdateSanctionsLists.php
git commit -m "refactor: extract HasSanctionsImport trait for sanctions commands"
```

---

## Task 3: Extract HasNotificationTesting Trait

**Priority:** Low — Nice-to-have for notification command cohesion

**Files:**
- Create: `app/Console/Commands/Concerns/HasNotificationTesting.php`
- Modify: `app/Console/Commands/TestNotification.php`
- Modify: `app/Console/Commands/SendNotificationDigest.php`

**Context:** `TestNotification` and `SendNotificationDigest` share notification dispatch logic. Extracting common methods reduces duplication.

- [ ] **Step 1: Analyze TestNotification and SendNotificationDigest**

Read both files to identify shared patterns.

- [ ] **Step 2: Create HasNotificationTesting trait**

Create `app/Console/Commands/Concerns/HasNotificationTesting.php`:

```php
<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Notification;

trait HasNotificationTesting
{
    protected function getTargetUsers(?int $userId = null): \Illuminate\Database\Eloquent\Collection
    {
        if ($userId) {
            return User::where('id', $userId)->where('is_active', true)->get();
        }

        return User::where('is_active', true)->get();
    }

    protected function sendTestNotification(User $user, $notification): void
    {
        Notification::send($user, $new $notification());
    }

    protected function formatNotificationResult(string $type, int $count): string
    {
        return "{$type} notification sent to {$count} user(s)";
    }
}
```

- [ ] **Step 3: Update TestNotification to use trait**

Add `use Concerns\HasNotificationTesting;` and replace duplicated methods.

- [ ] **Step 4: Update SendNotificationDigest to use trait**

Add `use Concerns\HasNotificationTesting;` and replace duplicated methods.

- [ ] **Step 5: Run affected tests**

Run: `php artisan test --compact --filter=Notification`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/Concerns/HasNotificationTesting.php \
  app/Console/Commands/TestNotification.php \
  app/Console/Commands/SendNotificationDigest.php
git commit -m "refactor: extract HasNotificationTesting trait for notification commands"
```

---

## Task 4: Verify Cluster Cohesion Improvement

**Priority:** High — Validation step

**Files:**
- None (verification only)

**Context:** After extracting traits, re-run GitNexus analysis to verify cohesion improvement.

- [ ] **Step 1: Run GitNexus analyze**

```bash
npx gitnexus analyze
```

- [ ] **Step 2: Query Commands cluster cohesion**

```bash
npx gitnexus cypher --query "MATCH (c:Community {heuristicLabel: 'Commands'}) RETURN c.symbolCount, c.cohesion"
```

Expected: Cohesion should improve from 0.40 toward 0.55+ (partial improvement from trait extraction).

- [ ] **Step 3: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass.

- [ ] **Step 4: Commit (if any adjustments needed)**

```bash
git add -A
git commit -m "chore: verify commands cluster refactoring"
```

---

## Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --format agent`
Expected: All files pass

- [ ] **Step 3: Verify GitNexus index is current**

Run: `npx gitnexus status`
Expected: Index is up to date with latest commit
