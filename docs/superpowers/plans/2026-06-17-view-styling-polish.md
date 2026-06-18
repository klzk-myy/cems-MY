# View Styling Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the remaining design-system gaps found in the post-refactor audit: eliminate raw Tailwind colors from views, fix invalid/semantically wrong component usage, migrate lingering inline form controls, and add regression tests/documentation.

**Architecture:** Keep changes minimal and local. Every fix replaces hand-rolled or incorrect markup with the existing tokenized component library (`<x-checkbox>`, `<x-textarea>`, `<x-card>`, `<x-link>`, etc.). Tests are added as PHPUnit view tests that assert the rendered Blade source no longer contains the offending patterns.

**Tech Stack:** Laravel 10, Blade, Tailwind CSS v4, PHPUnit 10, Laravel Pint.

---

## File Structure

| File | Responsibility |
|---|---|
| `resources/views/mfa/trusted-devices.blade.php` | Remove raw blue colors from icon circle |
| `resources/views/pages/mfa/verify.blade.php` | Remove raw blue link; replace hand-rolled card with `<x-card>` |
| `resources/views/compliance/sanctions/show.blade.php` | Remove raw blue link colors |
| `resources/views/compliance/sanctions/index.blade.php` | Remove raw blue link colors |
| `resources/views/reports/compliance-summary.blade.php` | Remove raw blue text color |
| `resources/views/test-results/statistics.blade.php` | Remove raw green/red/yellow legend dots |
| `resources/views/setup/index.blade.php` | Replace invalid `<x-input type="textarea">` with `<x-textarea>`; use `text-on-primary` |
| `resources/views/accounting/reconciliation.blade.php` | Replace inline reconciliation checkboxes with `<x-checkbox>` |
| `tests/Feature/Views/RawColorCleanupTest.php` | New regression tests for raw-color cleanup |
| `tests/Feature/Views/SharedComponentFormsTest.php` | Add tests for textarea/text-on-primary/checkbox migration |
| `tests/Feature/Views/ComponentConsistencyTest.php` | Add tests for `<x-card>` on MFA verify and new components |
| `resources/views/components/link.blade.php` | New semantic link component |
| `resources/views/components/status-dot.blade.php` | New status dot component |
| `resources/views/components/icon-circle.blade.php` | New colored icon circle component |
| `docs/view-styling-gap-analysis.md` | Update audit state and remaining exceptions |

---

## Phase 1: Raw Color Cleanup

### Task 1: Trusted Devices — Icon Circle

**Files:**
- Modify: `resources/views/mfa/trusted-devices.blade.php:11-12`
- Test: `tests/Feature/Views/RawColorCleanupTest.php` (to be created in Task 13)

- [ ] **Step 1: Replace raw blue colors with semantic tokens**

Current markup in `resources/views/mfa/trusted-devices.blade.php`:

```blade
<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```

New markup:

```blade
<div class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center">
    <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
```

- [ ] **Step 2: Run focused view tests**

Run: `php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add resources/views/mfa/trusted-devices.blade.php
git commit -m "style: use semantic info tokens in trusted devices icon circle"
```

---

### Task 2: MFA Verify — Recovery Link

**Files:**
- Modify: `resources/views/pages/mfa/verify.blade.php:15`
- Test: `tests/Feature/Views/RawColorCleanupTest.php`

- [ ] **Step 1: Replace raw blue link color**

Current markup:

```blade
<a href="{{ route('mfa.recovery') }}" class="text-blue-600 hover:underline">Use Recovery Code</a>
```

New markup:

```blade
<a href="{{ route('mfa.recovery') }}" class="text-info hover:text-info-hover hover:underline">Use Recovery Code</a>
```

- [ ] **Step 2: Run focused view tests**

Run: `php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add resources/views/pages/mfa/verify.blade.php
git commit -m "style: use semantic info tokens for mfa recovery link"
```

---

### Task 3: Sanctions Show — Source URL Link

**Files:**
- Modify: `resources/views/compliance/sanctions/show.blade.php:35`
- Test: `tests/Feature/Views/RawColorCleanupTest.php`

- [ ] **Step 1: Replace raw blue link colors**

Current markup:

```blade
<a href="{{ $list->source_url }}" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800">
```

New markup:

```blade
<a href="{{ $list->source_url }}" target="_blank" rel="noopener" class="text-info hover:text-info-hover">
```

- [ ] **Step 2: Run focused view tests**

Run: `php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add resources/views/compliance/sanctions/show.blade.php
git commit -m "style: use semantic info tokens for sanctions source url"
```

---

### Task 4: Sanctions Index — Source URL Link

**Files:**
- Modify: `resources/views/compliance/sanctions/index.blade.php:28`
- Test: `tests/Feature/Views/RawColorCleanupTest.php`

- [ ] **Step 1: Replace raw blue link colors**

Current markup:

```blade
<a href="{{ $list['source_url'] }}" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800">
```

New markup:

```blade
<a href="{{ $list['source_url'] }}" target="_blank" rel="noopener" class="text-info hover:text-info-hover">
```

- [ ] **Step 2: Run focused view tests**

Run: `php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add resources/views/compliance/sanctions/index.blade.php
git commit -m "style: use semantic info tokens for sanctions index source url"
```

---

### Task 5: Compliance Summary — EDD Count

**Files:**
- Modify: `resources/views/reports/compliance-summary.blade.php:53`
- Test: `tests/Feature/Views/RawColorCleanupTest.php`

- [ ] **Step 1: Replace raw blue text color**

Current markup:

```blade
<span class="text-sm font-medium text-blue-600">{{ number_format($eddCount) }}</span>
```

New markup:

```blade
<span class="text-sm font-medium text-info">{{ number_format($eddCount) }}</span>
```

- [ ] **Step 2: Run focused view tests**

Run: `php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add resources/views/reports/compliance-summary.blade.php
git commit -m "style: use semantic info token for edd count"
```

---

### Task 6: Test Results Statistics — Legend Dots

**Files:**
- Modify: `resources/views/test-results/statistics.blade.php:112,115,118`
- Test: `tests/Feature/Views/RawColorCleanupTest.php`

- [ ] **Step 1: Replace raw color dots with semantic tokens**

Current markup:

```blade
<span class="w-3 h-3 rounded-full bg-green-500"></span>
<span class="w-3 h-3 rounded-full bg-red-500"></span>
<span class="w-3 h-3 rounded-full bg-yellow-500"></span>
```

New markup:

```blade
<span class="w-3 h-3 rounded-full bg-success"></span>
<span class="w-3 h-3 rounded-full bg-danger"></span>
<span class="w-3 h-3 rounded-full bg-warning"></span>
```

- [ ] **Step 2: Run focused view tests**

Run: `php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add resources/views/test-results/statistics.blade.php
git commit -m "style: use semantic status tokens for test statistics legend"
```

---

## Phase 2: Component Usage Bug Fixes

### Task 7: Setup — Replace Invalid `<x-input type="textarea">`

**Files:**
- Modify: `resources/views/setup/index.blade.php:57`
- Test: `tests/Feature/Views/SharedComponentFormsTest.php`

- [ ] **Step 1: Replace with `<x-textarea>`**

Current markup:

```blade
<x-input type="textarea" name="business_address" label="Address" rows="2" inline />
```

New markup:

```blade
<x-textarea name="business_address" label="Address" :rows="2" inline>{{ old('business_address') }}</x-textarea>
```

- [ ] **Step 2: Add regression test**

Add to `tests/Feature/Views/SharedComponentFormsTest.php`:

```php
public function test_setup_index_uses_textarea_component(): void
{
    $path = $this->getViewPath('setup.index');
    $content = file_get_contents($path);

    $this->assertStringNotContainsString('type="textarea"', $content);
    $this->assertStringContainsString('<x-textarea', $content);
    $this->assertStringContainsString('name="business_address"', $content);
}
```

- [ ] **Step 3: Run the new test**

Run: `php artisan test --compact --filter=test_setup_index_uses_textarea_component`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/views/setup/index.blade.php tests/Feature/Views/SharedComponentFormsTest.php
git commit -m "fix: replace invalid x-input type=textarea with x-textarea in setup"
```

---

### Task 8: Setup — Fix Primary Foreground Token

**Files:**
- Modify: `resources/views/setup/index.blade.php:35`
- Test: `tests/Feature/Views/SharedComponentFormsTest.php`

- [ ] **Step 1: Replace `text-white` with `text-on-primary`**

Current markup:

```blade
<div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $i <= $currentStep ? 'bg-primary text-white' : 'bg-canvas-subtle text-ink-muted' }}">
```

New markup:

```blade
<div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $i <= $currentStep ? 'bg-primary text-on-primary' : 'bg-canvas-subtle text-ink-muted' }}">
```

- [ ] **Step 2: Add regression test**

Add to `tests/Feature/Views/SharedComponentFormsTest.php`:

```php
public function test_setup_index_uses_on_primary_foreground(): void
{
    $path = $this->getViewPath('setup.index');
    $content = file_get_contents($path);

    $this->assertStringContainsString('bg-primary text-on-primary', $content);
    $this->assertStringNotContainsString("bg-primary text-white", $content);
}
```

- [ ] **Step 3: Run the new test**

Run: `php artisan test --compact --filter=test_setup_index_uses_on_primary_foreground`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/views/setup/index.blade.php tests/Feature/Views/SharedComponentFormsTest.php
git commit -m "fix: use text-on-primary token in setup step indicator"
```

---

### Task 9: MFA Verify — Replace Hand-Rolled Card

**Files:**
- Modify: `resources/views/pages/mfa/verify.blade.php:5-17`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Replace `<div>` wrapper with `<x-card>`**

Current markup:

```blade
<div class="max-w-lg bg-surface rounded-lg shadow p-6">
    <p class="text-ink-muted mb-4">Enter the 6-digit code from your authenticator app.</p>

    <form method="POST" action="{{ route('mfa.verify.store') }}">
        @csrf
        <x-input type="text" name="code" label="Verification Code" placeholder="Enter 6-digit code" maxlength="6" required autofocus />
        <x-button type="submit" variant="primary" class="w-full">Verify</x-button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('mfa.recovery') }}" class="text-info hover:text-info-hover hover:underline">Use Recovery Code</a>
    </div>
</div>
```

New markup:

```blade
<x-card class="max-w-lg shadow">
    <p class="text-ink-muted mb-4">Enter the 6-digit code from your authenticator app.</p>

    <form method="POST" action="{{ route('mfa.verify.store') }}">
        @csrf
        <x-input type="text" name="code" label="Verification Code" placeholder="Enter 6-digit code" maxlength="6" required autofocus />
        <x-button type="submit" variant="primary" class="w-full">Verify</x-button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('mfa.recovery') }}" class="text-info hover:text-info-hover hover:underline">Use Recovery Code</a>
    </div>
</x-card>
```

- [ ] **Step 2: Add regression test**

Add to `tests/Feature/Views/ComponentConsistencyTest.php` in `forwardingComponentProvider`:

```php
'mfa-verify-card' => ['pages.mfa.verify', []],
```

Then add a dedicated test method:

```php
public function test_mfa_verify_uses_card_component(): void
{
    $path = resource_path('views/pages/mfa/verify.blade.php');
    $content = file_get_contents($path);

    $this->assertStringContainsString('<x-card', $content);
    $this->assertStringNotContainsString('bg-surface rounded-lg shadow p-6', $content);
}
```

- [ ] **Step 3: Run the new test**

Run: `php artisan test --compact --filter=test_mfa_verify_uses_card_component`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/views/pages/mfa/verify.blade.php tests/Feature/Views/ComponentConsistencyTest.php
git commit -m "refactor: use x-card in mfa verify page"
```

---

### Task 10: Bank Reconciliation — Inline Checkboxes

**Files:**
- Modify: `resources/views/accounting/reconciliation.blade.php:45,53,61,87,95`
- Test: `tests/Feature/Views/SharedComponentFormsTest.php`

- [ ] **Step 1: Replace inline checkboxes with `<x-checkbox>`**

Current markup (repeated 5 times):

```blade
<input type="checkbox" class="h-4 w-4 rounded border-border">
```

New markup:

```blade
<x-checkbox inline />
```

Apply to all 5 occurrences in the Outstanding Checks and Deposits in Transit tables.

- [ ] **Step 2: Add regression test**

Add to `tests/Feature/Views/SharedComponentFormsTest.php`:

```php
public function test_bank_reconciliation_uses_checkbox_components(): void
{
    $path = $this->getViewPath('accounting.reconciliation');
    $content = file_get_contents($path);

    $this->assertStringNotContainsString('<input type="checkbox"', $content);
    $this->assertStringContainsString('<x-checkbox', $content);
}
```

- [ ] **Step 3: Run the new test**

Run: `php artisan test --compact --filter=test_bank_reconciliation_uses_checkbox_components`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/views/accounting/reconciliation.blade.php tests/Feature/Views/SharedComponentFormsTest.php
git commit -m "refactor: use x-checkbox in bank reconciliation table"
```

---

## Phase 3: Regression Test Suite

### Task 11: Create `RawColorCleanupTest`

**Files:**
- Create: `tests/Feature/Views/RawColorCleanupTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

namespace Tests\Feature\Views;

use Tests\TestCase;

class RawColorCleanupTest extends TestCase
{
    private function assertViewHasNoRawColor(string $view, array $rawColors): void
    {
        $path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
        $content = file_get_contents($path);

        foreach ($rawColors as $color) {
            $this->assertStringNotContainsString($color, $content, "View {$view} should not contain {$color}.");
        }
    }

    public function test_trusted_devices_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('mfa.trusted-devices', ['bg-blue-100', 'text-blue-600']);
    }

    public function test_mfa_verify_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('pages.mfa.verify', ['text-blue-600']);
    }

    public function test_sanctions_show_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('compliance.sanctions.show', ['text-blue-600', 'hover:text-blue-800']);
    }

    public function test_sanctions_index_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('compliance.sanctions.index', ['text-blue-600', 'hover:text-blue-800']);
    }

    public function test_compliance_summary_has_no_raw_blue(): void
    {
        $this->assertViewHasNoRawColor('reports.compliance-summary', ['text-blue-600']);
    }

    public function test_test_results_statistics_has_no_raw_status_colors(): void
    {
        $this->assertViewHasNoRawColor('test-results.statistics', ['bg-green-500', 'bg-red-500', 'bg-yellow-500']);
    }
}
```

- [ ] **Step 2: Run the full new test class**

Run: `php artisan test --compact tests/Feature/Views/RawColorCleanupTest.php`
Expected: PASS (after Phase 1 tasks are complete)

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Views/RawColorCleanupTest.php
git commit -m "test: add regression tests for raw color cleanup"
```

---

### Task 12: Extend `ThemeTokenUsageTest` for New Component Variants

**Files:**
- Modify: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Add cases for info link and status dots**

Add to `themedComponentProvider`:

```php
'info-link-text' => ['pages.mfa.verify', [], ['text-info']],
'success-status-dot' => ['test-results.statistics', [], ['bg-success']],
'danger-status-dot' => ['test-results.statistics', [], ['bg-danger']],
'warning-status-dot' => ['test-results.statistics', [], ['bg-warning']],
```

- [ ] **Step 2: Run the updated test class**

Run: `php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "test: add token usage cases for info links and status dots"
```

---

## Phase 4: Documentation Update

### Task 13: Update `docs/view-styling-gap-analysis.md`

**Files:**
- Modify: `docs/view-styling-gap-analysis.md`

- [ ] **Step 1: Update Section 1 (What Has Been Completed)**

Change the table row count from 95 to 96:

```markdown
| **Core Pages Migrated** | ✅ Done | 93 of 96 non-email/non-vendor views use `<x-app-layout>` |
```

- [ ] **Step 2: Update Section 2 (Audit Results)**

Replace the issue summary table with the post-cleanup reality:

```markdown
| Category | Occurrences | Files Affected |
|----------|------------:|---------------:|
| Raw Tailwind status colors in views | 0 | 0 |
| Inline `<textarea>` elements | 0 | 0 |
| Invalid `<x-input type="textarea">` | 0 | 0 |
| Inline `<button>` elements | 0 | 0 |
| Inline `<table>` elements | 0 | 0 (excluding print/PDF views) |
| Inline `<input type="checkbox">` in views | 0 | 0 |
| Raw blue/green/red/yellow colors in views | 0 | 0 |
| Views without `<x-app-layout>` | 3 | 3 (login, EOD PDF, receipt) |
```

- [ ] **Step 3: Update Section 6 (Conclusion)**

Update the remaining exceptions list:

```markdown
**Remaining Exceptions (Acceptable):**
- `resources/views/reports/eod-reconciliation.blade.php` is a print/PDF-optimized view that intentionally uses inline CSS.
- `resources/views/transactions/receipt.blade.php` is a thermal/PDF receipt view that intentionally uses inline CSS.
- `resources/views/auth/login.blade.php` is a standalone authentication page without the app shell.
- `resources/views/components/button.blade.php` retains raw `indigo`, `purple`, and `teal` brand-color variants as a documented exception.
- `resources/views/components/alert.blade.php` uses `hover:bg-black/5` on the dismiss button as a translucent overlay effect.
```

- [ ] **Step 4: Commit**

```bash
git add docs/view-styling-gap-analysis.md
git commit -m "docs: update view styling gap analysis after cleanup"
```

---

## Phase 5: New Shared Components (Optional but Recommended)

### Task 14: Create `<x-link>` Component

**Files:**
- Create: `resources/views/components/link.blade.php`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Create the component**

```blade
@props(['href' => '#', 'variant' => 'default', 'external' => false])

@php
$variantClass = match($variant) {
    'info' => 'text-info hover:text-info-hover',
    'danger' => 'text-danger hover:text-danger-hover',
    'muted' => 'text-ink-muted hover:text-ink',
    default => 'text-primary hover:text-primary-hover',
};
@endphp

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => "{$variantClass} hover:underline transition-colors"]) }}
   @if($external) target="_blank" rel="noopener" @endif>
    {{ $slot }}
</a>
```

- [ ] **Step 2: Add consistency test**

Add to `ComponentConsistencyTest::forwardingComponentProvider`:

```php
'link' => ['components.link', ['href' => '/dashboard', 'slot' => 'Dashboard']],
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact tests/Feature/Views/ComponentConsistencyTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/link.blade.php tests/Feature/Views/ComponentConsistencyTest.php
git commit -m "feat: add semantic x-link component"
```

---

### Task 15: Create `<x-status-dot>` Component

**Files:**
- Create: `resources/views/components/status-dot.blade.php`
- Modify: `resources/views/test-results/statistics.blade.php:112,115,118`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Create the component**

```blade
@props(['color' => 'gray', 'size' => 'sm'])

@php
$colorClass = match($color) {
    'success' => 'bg-success',
    'danger' => 'bg-danger',
    'warning' => 'bg-warning',
    'info' => 'bg-info',
    default => 'bg-canvas-subtle',
};

$sizeClass = match($size) {
    'sm' => 'w-2 h-2',
    'md' => 'w-3 h-3',
    'lg' => 'w-4 h-4',
    default => 'w-3 h-3',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-block rounded-full {$sizeClass} {$colorClass}"]) }}></span>
```

- [ ] **Step 2: Replace legend dots in test-results/statistics**

Current:

```blade
<span class="w-3 h-3 rounded-full bg-success"></span>
<span class="w-3 h-3 rounded-full bg-danger"></span>
<span class="w-3 h-3 rounded-full bg-warning"></span>
```

New:

```blade
<x-status-dot color="success" />
<x-status-dot color="danger" />
<x-status-dot color="warning" />
```

- [ ] **Step 3: Add token usage test**

Add to `ThemeTokenUsageTest::themedComponentProvider`:

```php
'status-dot-success' => ['components.status-dot', ['color' => 'success'], ['bg-success']],
'status-dot-danger' => ['components.status-dot', ['color' => 'danger'], ['bg-danger']],
'status-dot-warning' => ['components.status-dot', ['color' => 'warning'], ['bg-warning']],
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Views/ComponentConsistencyTest.php tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/status-dot.blade.php resources/views/test-results/statistics.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "feat: add x-status-dot component and use it in test statistics"
```

---

### Task 16: Create `<x-icon-circle>` Component

**Files:**
- Create: `resources/views/components/icon-circle.blade.php`
- Modify: `resources/views/mfa/trusted-devices.blade.php:11-15`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Create the component**

```blade
@props(['color' => 'info', 'size' => 'md'])

@php
$colorClass = match($color) {
    'info' => 'bg-info-subtle text-info',
    'success' => 'bg-success-subtle text-success-text',
    'danger' => 'bg-danger-subtle text-danger-text',
    'warning' => 'bg-warning-subtle text-warning-text',
    default => 'bg-canvas-subtle text-ink-muted',
};

$sizeClass = match($size) {
    'sm' => 'w-8 h-8',
    'md' => 'w-10 h-10',
    'lg' => 'w-12 h-12',
    default => 'w-10 h-10',
};
@endphp

<div {{ $attributes->merge(['class' => "{$sizeClass} {$colorClass} rounded-full flex items-center justify-center"]) }}>
    {{ $slot }}
</div>
```

- [ ] **Step 2: Replace icon circle in trusted-devices**

Current:

```blade
<div class="w-10 h-10 rounded-full bg-info-subtle flex items-center justify-center">
    <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>
</div>
```

New:

```blade
<x-icon-circle color="info">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>
</x-icon-circle>
```

- [ ] **Step 3: Add consistency test**

Add to `ComponentConsistencyTest::forwardingComponentProvider`:

```php
'icon-circle' => ['components.icon-circle', ['color' => 'info', 'slot' => '']],
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Views/ComponentConsistencyTest.php tests/Feature/Views/ThemeTokenUsageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/icon-circle.blade.php resources/views/mfa/trusted-devices.blade.php tests/Feature/Views/ComponentConsistencyTest.php
git commit -m "feat: add x-icon-circle component and use it in trusted devices"
```

---

## Phase 6: Final Verification

### Task 17: Run Full Verification Suite

- [ ] **Step 1: Run all view tests**

Run: `php artisan test --compact tests/Feature/Views/`
Expected: PASS

- [ ] **Step 2: Run linting**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no changes

- [ ] **Step 3: Build frontend assets**

Run: `npm run build`
Expected: successful build

- [ ] **Step 4: Run targeted raw-color grep**

Run:

```bash
grep -R -E '\b(bg|text|border)-(red|green|blue|yellow|indigo|purple|teal|orange|cyan|pink)-[0-9]{2,3}\b' resources/views --include='*.blade.php' | grep -v 'resources/views/components/button.blade.php'
```

Expected: no output

- [ ] **Step 5: Commit any lint fixes**

```bash
git add -A
git commit -m "chore: apply pint formatting after view styling polish"
```

---

## Self-Review

### 1. Spec coverage

| Finding from audit | Task |
|---|---|
| Raw blue colors in `mfa/trusted-devices` | Task 1 |
| Raw blue link in `pages/mfa/verify` | Task 2 |
| Raw blue links in `compliance/sanctions/show` | Task 3 |
| Raw blue links in `compliance/sanctions/index` | Task 4 |
| Raw blue text in `reports/compliance-summary` | Task 5 |
| Raw green/red/yellow dots in `test-results/statistics` | Task 6 + Task 15 |
| Invalid `<x-input type="textarea">` in `setup/index` | Task 7 |
| `text-white` instead of `text-on-primary` in `setup/index` | Task 8 |
| Hand-rolled card in `pages/mfa/verify` | Task 9 |
| Inline checkboxes in `accounting/reconciliation` | Task 10 |
| Missing regression tests | Tasks 11–12 |
| Outdated gap-analysis doc | Task 13 |
| Missing link/status-dot/icon-circle components | Tasks 14–16 |

### 2. Placeholder scan

- No `TBD`, `TODO`, or `implement later` placeholders.
- Every code step contains the actual before/after markup or test code.
- Every test command includes the expected outcome.

### 3. Type consistency

- Component prop names (`color`, `variant`, `size`, `href`, `external`) are consistent across new components.
- Test method names follow the existing `test_*` convention.
- View path helper from `SharedComponentFormsTest` is reused for new tests.

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-06-17-view-styling-polish.md`.**

Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using batch execution with checkpoints for review.

Which approach would you like?
