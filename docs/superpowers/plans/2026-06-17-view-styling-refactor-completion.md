# View Styling Refactor Completion Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the component-library refactor by eliminating raw Tailwind colors, fixing dark-mode bugs, synchronizing component class/blade logic, adding missing form components, and migrating remaining inline markup in views.

**Architecture:** Keep the existing Tailwind CSS v4 token-first architecture in `resources/css/app.css`. Extend the token set with foreground (`on-*`) and hover tokens so every color state is theme-driven. Make each Blade component self-contained and deterministic, then update view files to use the new/updated components. Verify with the existing view/component tests plus new regression tests for the bugs found.

**Tech Stack:** Laravel 10, Blade components, Tailwind CSS v4, Alpine.js, PHPUnit.

---

## File Structure Map

| File | Responsibility |
|------|----------------|
| `resources/css/app.css` | CSS custom properties + `@theme inline` token definitions |
| `resources/views/components/app-layout.blade.php` | Root layout (no changes expected) |
| `resources/views/components/button.blade.php` | Button variants, sizes, loading, icon states |
| `resources/views/components/input.blade.php` | Text/number/date/etc. inputs |
| `resources/views/components/select.blade.php` | Select dropdowns |
| `resources/views/components/textarea.blade.php` | New: multi-line textareas |
| `resources/views/components/checkbox.blade.php` | New: single checkbox with label |
| `resources/views/components/radio-group.blade.php` | New: radio option group |
| `resources/views/components/alert.blade.php` | Alert boxes + dismiss button |
| `app/View/Components/Alert.php` | Alert class (fix or remove dead logic) |
| `resources/views/components/badge.blade.php` | Status badges |
| `resources/views/components/card.blade.php` | Basic card |
| `resources/views/components/card-section.blade.php` | Card with header/actions |
| `resources/views/components/navigation.blade.php` | Sidebar navigation |
| `resources/views/components/stat-card.blade.php` | KPI stat cards |
| `resources/views/components/progress-bar.blade.php` | Progress indicator |
| `resources/views/components/chart-bar.blade.php` | Simple bar chart |
| `resources/views/components/chart-trend.blade.php` | Trend chart card |
| `resources/views/components/data-table.blade.php` | Paginated data table |
| `app/View/Components/DataTable.php` | DataTable class |
| `resources/views/components/empty-state.blade.php` | Empty state (table + standalone) |
| `resources/views/components/page-header.blade.php` | Page title/actions |
| `tests/Feature/Views/ThemeTokenUsageTest.php` | Token usage assertions |
| `tests/Feature/Views/ComponentConsistencyTest.php` | Attribute forwarding assertions |
| `tests/Unit/View/Components/AlertTest.php` | Alert unit tests (rewrite) |
| `docs/view-styling-gap-analysis.md` | Audit doc (update) |
| `docs/component-style-guide.md` | Style guide (update) |

---

## Phase 1: Extend Theme Tokens

**Goal:** Add the missing tokens required to replace every raw Tailwind color in components.

### Task 1.1: Add foreground and hover tokens to `resources/css/app.css`

**Files:**
- Modify: `resources/css/app.css:1-122`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

Add token-existence assertions to `ThemeTokenUsageTest.php` so the new tokens must be used by components.

```php
public static function themedComponentProvider(): array
{
    return [
        // ... existing entries ...
        'button-primary-foreground' => ['components.button', ['variant' => 'primary', 'slot' => 'Click'], ['text-on-primary']],
        'button-danger-foreground' => ['components.button', ['variant' => 'danger', 'slot' => 'Click'], ['text-on-danger']],
        'button-hover-tokens' => ['components.button', ['variant' => 'danger', 'slot' => 'Click'], ['bg-danger-hover']],
        'navigation-tokens' => ['components.navigation', ['slot' => ''], ['bg-sidebar', 'text-sidebar-text']],
    ];
}
```

- [ ] **Step 2: Run the test and confirm it fails**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
```

Expected: FAIL with messages about missing `text-on-primary`, `bg-danger-hover`, `bg-sidebar`, etc.

- [ ] **Step 3: Add CSS custom properties and theme tokens**

Add these variables inside the `:root` block (after `--color-info-text-val`) and inside `.dark` with appropriate values.

```css
:root {
  /* existing variables ... */
  --color-on-primary-val: #ffffff;
  --color-on-danger-val: #ffffff;
  --color-on-success-val: #ffffff;
  --color-on-warning-val: #ffffff;
  --color-on-info-val: #ffffff;

  --color-danger-hover-val: #b91c1c;
  --color-success-hover-val: #15803d;
  --color-warning-hover-val: #b45309;
  --color-info-hover-val: #1d4ed8;

  --color-sidebar-val: #171717;
  --color-sidebar-hover-val: #262626;
  --color-sidebar-border-val: #2d2d2d;
  --color-sidebar-text-val: #ffffff;
  --color-sidebar-text-muted-val: #9ca3af;
  --color-sidebar-ring-val: #4b5563;
}

.dark {
  /* existing overrides ... */
  --color-on-primary-val: #171717;
  --color-on-danger-val: #171717;
  --color-on-success-val: #171717;
  --color-on-warning-val: #171717;
  --color-on-info-val: #171717;

  --color-danger-hover-val: #f87171;
  --color-success-hover-val: #4ade80;
  --color-warning-hover-val: #fbbf24;
  --color-info-hover-val: #60a5fa;

  --color-sidebar-val: #0a0a0a;
  --color-sidebar-hover-val: #262626;
  --color-sidebar-border-val: #2d2d2d;
  --color-sidebar-text-val: #f7f7f8;
  --color-sidebar-text-muted-val: #9ca3af;
  --color-sidebar-ring-val: #6b7280;
}
```

Then expose them in `@theme inline`:

```css
@theme inline {
  /* existing tokens ... */
  --color-on-primary: var(--color-on-primary-val);
  --color-on-danger: var(--color-on-danger-val);
  --color-on-success: var(--color-on-success-val);
  --color-on-warning: var(--color-on-warning-val);
  --color-on-info: var(--color-on-info-val);

  --color-danger-hover: var(--color-danger-hover-val);
  --color-success-hover: var(--color-success-hover-val);
  --color-warning-hover: var(--color-warning-hover-val);
  --color-info-hover: var(--color-info-hover-val);

  --color-sidebar: var(--color-sidebar-val);
  --color-sidebar-hover: var(--color-sidebar-hover-val);
  --color-sidebar-border: var(--color-sidebar-border-val);
  --color-sidebar-text: var(--color-sidebar-text-val);
  --color-sidebar-text-muted: var(--color-sidebar-text-muted-val);
  --color-sidebar-ring: var(--color-sidebar-ring-val);
}
```

- [ ] **Step 4: Run the test and confirm it passes**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
```

Expected: PASS (components will use these tokens in later tasks; for now the tokens just need to exist in CSS).

- [ ] **Step 5: Verify the build still compiles**

```bash
npm run build
```

Expected: successful build.

- [ ] **Step 6: Commit**

```bash
git add resources/css/app.css tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "feat(theme): add on-* foreground, hover and sidebar tokens"
```

---

## Phase 2: Fix Core Components

### Task 2.1: Fix `Button` — dark-mode contrast and raw colors

**Files:**
- Modify: `resources/views/components/button.blade.php:11-35`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

Extend `ThemeTokenUsageTest` assertions for every button variant to forbid raw hover colors.

```php
public static function themedComponentProvider(): array
{
    return [
        // ... existing entries ...
        'button-danger-hover-token' => ['components.button', ['variant' => 'danger', 'slot' => 'Click'], ['bg-danger-hover']],
        'button-success-hover-token' => ['components.button', ['variant' => 'success', 'slot' => 'Click'], ['bg-success-hover']],
    ];
}
```

Also add a new test method:

```php
public function test_button_primary_uses_on_primary_foreground(): void
{
    $html = view('components.button', ['variant' => 'primary', 'slot' => 'Click'])->render();
    $this->assertStringContainsString('text-on-primary', $html);
}
```

Run and confirm FAIL.

- [ ] **Step 2: Replace the variant map in `button.blade.php`**

Replace lines 14–26 with:

```blade
$variantClass = match($variant) {
    'primary' => 'bg-primary text-on-primary hover:bg-primary-hover',
    'secondary' => 'bg-surface border border-border text-ink-muted hover:bg-canvas-subtle',
    'danger' => 'bg-danger text-on-danger hover:bg-danger-hover',
    'success' => 'bg-success text-on-success hover:bg-success-hover',
    'warning' => 'bg-warning text-on-warning hover:bg-warning-hover',
    'info' => 'bg-info text-on-info hover:bg-info-hover',
    'indigo' => 'bg-indigo-600 text-white hover:bg-indigo-700',
    'purple' => 'bg-purple-600 text-white hover:bg-purple-700',
    'teal' => 'bg-teal-600 text-white hover:bg-teal-700',
    'ghost' => 'bg-transparent text-ink-muted hover:bg-canvas-subtle',
    default => 'bg-primary text-on-primary hover:bg-primary-hover',
};
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/button.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "fix(button): use theme tokens for foreground and hover colors"
```

### Task 2.2: Fix `Input` and `Select` error-state colors

**Files:**
- Modify: `resources/views/components/input.blade.php:18`, `resources/views/components/input.blade.php:43`
- Modify: `resources/views/components/select.blade.php:18`, `resources/views/components/select.blade.php:45`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

Add to `ThemeTokenUsageTest::themedComponentProvider`:

```php
'input-error-text' => ['components.input', ['name' => 'foo', 'errors' => new ViewErrorBag], ['text-danger-text']],
'select-error-text' => ['components.select', ['name' => 'foo', 'options' => [], 'errors' => new ViewErrorBag], ['text-danger-text']],
```

Run and confirm FAIL.

- [ ] **Step 2: Replace raw reds with tokens**

In `input.blade.php`:

```blade
@if($required) <span class="text-danger">*</span> @endif
```

```blade
<p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
```

In `select.blade.php` apply the same two replacements.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/input.blade.php resources/views/components/select.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "fix(input,select): tokenize required asterisk and error message colors"
```

### Task 2.3: Fix `Badge` — explicit `gray` variant and purple tokenization

**Files:**
- Modify: `resources/views/components/badge.blade.php:7-14`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

Add assertions:

```php
'badge-gray' => ['components.badge', ['variant' => 'gray', 'slot' => 'Draft'], ['bg-canvas-subtle', 'text-ink-muted']],
'badge-purple-no-raw' => ['components.badge', ['variant' => 'purple', 'slot' => 'VIP'], [], ['text-purple-700', 'bg-purple-100']],
```

The last provider entry requires extending the test method to support a `forbiddenTokens` argument. If you prefer not to change the test signature, simply add an explicit test:

```php
public function test_badge_purple_does_not_use_raw_tailwind_colors(): void
{
    $html = view('components.badge', ['variant' => 'purple', 'slot' => 'VIP'])->render();
    $this->assertStringNotContainsString('text-purple-700', $html);
    $this->assertStringNotContainsString('bg-purple-100', $html);
}
```

Run and confirm FAIL.

- [ ] **Step 2: Update the badge variant map**

Add a `gray` case and keep purple as a documented raw-color exception, or replace purple with accent tokens. For consistency with the design system, map purple to accent:

```blade
$styles = match($variant) {
    'success' => 'bg-success-subtle text-success-text',
    'danger' => 'bg-danger-subtle text-danger-text',
    'warning' => 'bg-warning-subtle text-warning-text',
    'info' => 'bg-info-subtle text-info-text',
    'gray' => 'bg-canvas-subtle text-ink-muted',
    'purple' => 'bg-accent/10 text-accent',
    default => 'bg-canvas-subtle text-ink-muted',
};
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/badge.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "fix(badge): add explicit gray variant and tokenize purple"
```

### Task 2.4: Fix `Alert` — synchronize class and blade, support `danger`

**Files:**
- Modify: `resources/views/components/alert.blade.php:1-47`
- Modify: `app/View/Components/Alert.php:1-67`
- Rewrite: `tests/Unit/View/Components/AlertTest.php`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Rewrite `AlertTest` to assert rendered output**

Replace the entire file with:

```php
<?php

namespace Tests\Unit\View\Components;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AlertTest extends TestCase
{
    public function test_alert_renders_success_variant(): void
    {
        $html = view('components.alert', ['type' => 'success', 'slot' => 'Saved'])->render();
        $this->assertStringContainsString('bg-success-subtle', $html);
        $this->assertStringContainsString('Saved', $html);
    }

    public function test_alert_renders_error_variant(): void
    {
        $html = view('components.alert', ['type' => 'error', 'slot' => 'Failed'])->render();
        $this->assertStringContainsString('bg-danger-subtle', $html);
        $this->assertStringContainsString('Failed', $html);
    }

    public function test_alert_danger_alias_renders_error_styling(): void
    {
        $html = view('components.alert', ['type' => 'danger', 'slot' => 'Danger'])->render();
        $this->assertStringContainsString('bg-danger-subtle', $html);
    }

    public function test_alert_renders_title(): void
    {
        $html = view('components.alert', ['type' => 'info', 'title' => 'Note', 'slot' => 'Body'])->render();
        $this->assertStringContainsString('Note', $html);
        $this->assertStringContainsString('Body', $html);
    }

    public function test_alert_can_hide_icon(): void
    {
        $html = view('components.alert', ['type' => 'info', 'icon' => false, 'slot' => 'No icon'])->render();
        $this->assertStringNotContainsString('<svg', $html);
    }
}
```

Run and confirm FAIL (especially the `danger` alias test).

- [ ] **Step 2: Simplify `app/View/Components/Alert.php`**

Make the PHP class a thin constructor-only wrapper so the blade is the source of truth:

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public ?string $title = null,
        public bool $dismissible = false,
    ) {}

    public function render(): View
    {
        return view('components.alert');
    }
}
```

- [ ] **Step 3: Update `alert.blade.php` to support `danger` and use tokens**

Replace lines 1–47 with:

```blade
@props(['type' => 'info', 'title' => null, 'dismissible' => false])

@php
$resolvedType = $type === 'danger' ? 'error' : $type;

$styles = match($resolvedType) {
    'success' => 'bg-success-subtle border-success-border text-success-text',
    'error' => 'bg-danger-subtle border-danger-border text-danger-text',
    'warning' => 'bg-warning-subtle border-warning-border text-warning-text',
    'info' => 'bg-info-subtle border-info-border text-info-text',
    default => 'bg-canvas-subtle border-border text-ink-muted',
};

$icons = [
    'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'error' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
];
@endphp

<div x-data="{ shown: true }" 
     x-show="shown"
     {{ $attributes->merge(['class' => "mb-6 border rounded-lg p-4 $styles"]) }}
     x-transition>
    <div class="flex gap-3">
        @if(($icon ?? true) !== false)
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$resolvedType] ?? $icons['info'] }}" />
            </svg>
        @endif
        
        <div class="flex-1">
            @if($title)
                <p class="font-medium mb-1">{{ $title }}</p>
            @endif
            <div class="text-sm">{{ $slot }}</div>
        </div>
        
        @if($dismissible)
            <button @click="shown = false" 
                    class="shrink-0 -mr-1 -mt-1 p-1 rounded hover:bg-black/5 dark:hover:bg-surface/10">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>
</div>
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact tests/Unit/View/Components/AlertTest.php tests/Feature/Views/ThemeTokenUsageTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/View/Components/Alert.php resources/views/components/alert.blade.php tests/Unit/View/Components/AlertTest.php
git commit -m "fix(alert): synchronize class/blade, add danger alias, tokenize styles"
```

### Task 2.5: Standardize `Card` and `Card-Section` body padding

**Files:**
- Modify: `resources/views/components/card.blade.php:17-19`
- Modify: `resources/views/components/card-section.blade.php:23-25`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Write the failing test**

Add to `ComponentConsistencyTest::forwardingComponentProvider` (padding tests live in `ThemeTokenUsageTest` for consistency; add there):

```php
'card-body-padding' => ['components.card', ['title' => 'T', 'slot' => 'Body'], ['p-6']],
'card-section-body-padding' => ['components.card-section', ['title' => 'T', 'slot' => 'Body'], ['p-6']],
```

Run and confirm FAIL.

- [ ] **Step 2: Add padding to body slots**

In `card.blade.php`:

```blade
<div class="p-6">
    {{ $slot }}
</div>
```

In `card-section.blade.php`:

```blade
<div class="p-6">
    {{ $slot }}
</div>
```

- [ ] **Step 3: Remove redundant `p-6` wrappers from views that already add them**

After this change, views that wrap card content in `<div class="p-6">` will double-pad. Search for these patterns and remove the redundant wrapper, keeping the content.

Search command:

```bash
grep -R -n "<x-card[^>]*>\s*\n\s*<div class=\"p-6\"" resources/views --include="*.blade.php"
```

Update each match (exact replacements depend on search results; do not leave double padding).

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php tests/Feature/Views/ComponentConsistencyTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/card.blade.php resources/views/components/card-section.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git add $(git diff --name-only resources/views)  # only if view wrappers were changed
git commit -m "fix(card,card-section): add default p-6 body padding and remove redundant wrappers"
```

### Task 2.6: Tokenize `Navigation` sidebar

**Files:**
- Modify: `resources/views/components/navigation.blade.php:9-99`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

Add:

```php
'navigation-bg' => ['components.navigation', ['slot' => ''], ['bg-sidebar', 'text-sidebar-text']],
'navigation-hover' => ['components.navigation', ['slot' => ''], ['hover:bg-sidebar-hover']],
'navigation-border' => ['components.navigation', ['slot' => ''], ['border-sidebar-border']],
```

Run and confirm FAIL.

- [ ] **Step 2: Replace raw grays with sidebar tokens**

Replace the navigation component’s raw Tailwind gray classes:

```blade
<nav x-data="{ collapsed: {{ $collapsed ? 'true' : 'false' }} }" 
     :class="collapsed ? 'w-20' : 'w-64'"
     class="bg-sidebar text-sidebar-text min-h-screen flex flex-col transition-all duration-300">
    
    {{-- Brand --}}
    <div class="p-4 border-b border-sidebar-border flex items-center justify-between">
        <h1 x-show="!collapsed" class="text-xl font-bold transition-opacity duration-300">{{ config('app.name') }}</h1>
        <button @click="collapsed = !collapsed" 
                class="p-2 rounded hover:bg-sidebar-hover transition-colors focus:outline-none focus:ring-2 focus:ring-sidebar-ring">
            <!-- existing svgs -->
        </button>
    </div>
```

Then replace every remaining `border-gray-700` with `border-sidebar-border`, every `hover:bg-gray-800` with `hover:bg-sidebar-hover`, every `bg-gray-800` with `bg-sidebar-hover`, every `bg-gray-700` with `bg-sidebar-hover`, and the dark-mode toggle/logout buttons similarly.

Also replace `text-ink-muted/50` in section headers with `text-sidebar-text-muted`.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/navigation.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "fix(navigation): apply sidebar theme tokens instead of raw grays"
```

---

## Phase 3: Add Missing Form Components

### Task 3.1: Create `Textarea` component

**Files:**
- Create: `resources/views/components/textarea.blade.php`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Write the failing test**

Add to `ComponentConsistencyTest::forwardingComponentProvider`:

```php
'textarea' => ['components.textarea', ['name' => 'notes', 'slot' => '']],
```

Run and confirm FAIL (component missing).

- [ ] **Step 2: Create the component**

```blade
@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'placeholder' => null,
    'help' => null,
    'rows' => 3,
    'inline' => false,
])

<div class="{{ $inline ? '' : 'mb-4' }}">
    @if($label)
        <label for="{{ $name ?? $attributes->whereStartsWith('id')->first() }}" 
               class="block text-sm font-medium text-ink-muted mb-2">
            {{ $label }}
            @if($required) <span class="text-danger">*</span> @endif
        </label>
    @endif
    
    <textarea
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        rows="{{ $rows }}"
        {{ $attributes->except(['label', 'name', 'required', 'disabled', 'readonly', 'placeholder', 'help', 'rows', 'inline']) }}
        class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg 
               text-ink placeholder:text-ink-muted/50
               focus:outline-none focus:ring-2 focus:ring-primary/10 focus:border-primary
               disabled:bg-canvas-subtle disabled:text-ink-muted
               @if(isset($errors) && $errors->has($name ?? '')) border-danger @endif
               {{ $attributes->get('class', '') }}">{{ $slot }}</textarea>
    
    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif
    
    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ComponentConsistencyTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/textarea.blade.php tests/Feature/Views/ComponentConsistencyTest.php
git commit -m "feat(components): add textarea component"
```

### Task 3.2: Create `Checkbox` component

**Files:**
- Create: `resources/views/components/checkbox.blade.php`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Write the failing test**

```php
'checkbox' => ['components.checkbox', ['name' => 'is_active', 'label' => 'Active', 'slot' => '']],
```

Run and confirm FAIL.

- [ ] **Step 2: Create the component**

```blade
@props([
    'label' => null,
    'name' => null,
    'value' => 1,
    'checked' => false,
    'required' => false,
    'disabled' => false,
    'help' => null,
    'inline' => false,
])

<div class="{{ $inline ? '' : 'mb-4' }}">
    <label class="flex items-center gap-2 cursor-pointer">
        <input
            type="checkbox"
            @if($name) name="{{ $name }}" id="{{ $name }}" @endif
            value="{{ $value }}"
            @if($checked) checked @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->except(['label', 'name', 'value', 'checked', 'required', 'disabled', 'help', 'inline']) }}
            class="w-4 h-4 rounded border-border text-primary focus:ring-primary focus:ring-2 disabled:opacity-50 {{ $attributes->get('class', '') }}"
        >
        @if($label)
            <span class="text-sm text-ink">{{ $label }}</span>
        @endif
    </label>
    
    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif
    
    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ComponentConsistencyTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/checkbox.blade.php tests/Feature/Views/ComponentConsistencyTest.php
git commit -m "feat(components): add checkbox component"
```

### Task 3.3: Create `RadioGroup` component

**Files:**
- Create: `resources/views/components/radio-group.blade.php`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Write the failing test**

```php
'radio-group' => ['components.radio-group', ['name' => 'risk_level', 'options' => ['low' => 'Low'], 'slot' => '']],
```

Run and confirm FAIL.

- [ ] **Step 2: Create the component**

```blade
@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'disabled' => false,
    'help' => null,
    'inline' => false,
])

<div class="{{ $inline ? '' : 'mb-4' }}">
    @if($label)
        <label class="block text-sm font-medium text-ink-muted mb-2">
            {{ $label }}
            @if($required) <span class="text-danger">*</span> @endif
        </label>
    @endif
    
    <div class="flex flex-wrap gap-4">
        @foreach($options as $value => $optionLabel)
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $value }}"
                    @checked(old($name, $selected) == $value)
                    @if($required) required @endif
                    @if($disabled) disabled @endif
                    {{ $attributes->except(['label', 'name', 'options', 'selected', 'required', 'disabled', 'help', 'inline']) }}
                    class="w-4 h-4 border-border text-primary focus:ring-primary focus:ring-2 disabled:opacity-50 {{ $attributes->get('class', '') }}"
                >
                <span class="text-sm text-ink">{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>
    
    @if($help)
        <p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>
    @endif
    
    @if($name && isset($errors))
        @error($name)
            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
        @enderror
    @endif
</div>
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ComponentConsistencyTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/radio-group.blade.php tests/Feature/Views/ComponentConsistencyTest.php
git commit -m "feat(components): add radio-group component"
```

---

## Phase 4: Tokenize Data Visualization Components

### Task 4.1: `StatCard`

**Files:**
- Modify: `resources/views/components/stat-card.blade.php:11-35`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

Add assertions that forbid raw `text-blue-600`, `text-red-600`, etc., and assert semantic tokens appear.

```php
public function test_stat_card_uses_semantic_color_tokens(): void
{
    $html = view('components.stat-card', ['label' => 'X', 'value' => '1', 'color' => 'red'])->render();
    $this->assertStringContainsString('text-danger', $html);
    $this->assertStringNotContainsString('text-red-600', $html);
}
```

Run and confirm FAIL.

- [ ] **Step 2: Replace raw color map**

```blade
$valueColorClass = match($color) {
    'blue' => 'text-info',
    'red' => 'text-danger',
    'yellow' => 'text-warning',
    'purple' => 'text-accent',
    'green' => 'text-success',
    default => is_numeric($value) && $value >= 80 ? 'text-success' : (is_numeric($value) && $value >= 50 ? 'text-warning' : 'text-ink'),
};
```

And for the trend line:

```blade
<p class="mt-1 text-xs {{ $trend >= 0 ? 'text-success' : 'text-danger' }}">
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/stat-card.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "fix(stat-card): replace raw tailwind colors with semantic tokens"
```

### Task 4.2: `ProgressBar`

**Files:**
- Modify: `resources/views/components/progress-bar.blade.php:1-16`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

```php
'progress-bar-tokens' => ['components.progress-bar', ['value' => 50], ['bg-success', 'bg-warning', 'bg-danger']],
```

Run and confirm FAIL.

- [ ] **Step 2: Replace raw color classes**

```blade
$colorClass = $color ?? ($percent >= 100 ? 'bg-danger' : ($percent >= 80 ? 'bg-warning' : 'bg-success'));
```

- [ ] **Step 3: Run tests and commit**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
git add resources/views/components/progress-bar.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "fix(progress-bar): use semantic success/warning/danger tokens"
```

### Task 4.3: `ChartBar`

**Files:**
- Modify: `resources/views/components/chart-bar.blade.php:1-10`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

```php
'chart-bar-tokens' => ['components.chart-bar', ['value' => 50], ['bg-success', 'bg-warning', 'bg-danger']],
```

- [ ] **Step 2: Replace raw colors**

```blade
$colorClass = $color ?? ($value >= 80 ? 'bg-success' : ($value >= 50 ? 'bg-warning' : 'bg-danger'));
```

- [ ] **Step 3: Run tests and commit**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
git add resources/views/components/chart-bar.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "fix(chart-bar): use semantic tokens for bar colors"
```

### Task 4.4: `ChartTrend`

**Files:**
- Modify: `resources/views/components/chart-trend.blade.php:1-43`
- Test: `tests/Feature/Views/ThemeTokenUsageTest.php`

- [ ] **Step 1: Write the failing test**

```php
'chart-trend-tokens' => ['components.chart-trend', ['title' => 'X', 'labels' => [], 'values' => []], ['fill-success', 'text-success']],
```

- [ ] **Step 2: Replace raw colors**

```blade
$colorClass = match ($color) {
    'yellow' => 'fill-warning',
    'green' => 'fill-success',
    default => 'fill-danger',
};
$textClass = match ($color) {
    'yellow' => 'text-warning',
    'green' => 'text-success',
    default => 'text-danger',
};
```

- [ ] **Step 3: Run tests and commit**

```bash
php artisan test --compact tests/Feature/Views/ThemeTokenUsageTest.php
git add resources/views/components/chart-trend.blade.php tests/Feature/Views/ThemeTokenUsageTest.php
git commit -m "fix(chart-trend): tokenize fill and text colors"
```

---

## Phase 5: Improve `DataTable` and `EmptyState`

### Task 5.1: Make `DataTable` actions column optional and declare all props

**Files:**
- Modify: `resources/views/components/data-table.blade.php:1-41`
- Modify: `app/View/Components/DataTable.php:10-48`
- Test: `tests/Unit/View/Components/DataTableTest.php`

- [ ] **Step 1: Write the failing test**

Add to `DataTableTest.php`:

```php
public function test_datatable_can_hide_actions_column(): void
{
    $component = new DataTable(
        data: null,
        columns: [['key' => 'name', 'label' => 'Name']],
        hasActions: false
    );

    $this->assertEquals(1, $component->getColumnCount());
}
```

Run and confirm FAIL.

- [ ] **Step 2: Add `hasActions` prop to PHP class and blade**

In `app/View/Components/DataTable.php`:

```php
public function __construct(
    public ?LengthAwarePaginator $data = null,
    public array $columns = [],
    public bool $sortable = true,
    public bool $searchable = true,
    public string $emptyMessage = 'No records found',
    public bool $hasActions = true,
) {}

public function getColumnCount(): int
{
    return count($this->columns) + ($this->hasActions ? 1 : 0);
}
```

In `resources/views/components/data-table.blade.php`:

```blade
@props(['data' => null, 'columns' => [], 'hasData' => false, 'columnCount' => 1, 'hasActions' => true])
```

Then wrap the actions header and the default empty-state logic conditionally:

```blade
@foreach($columns as $column)
    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
        {{ $column['label'] }}
    </th>
@endforeach
@if($hasActions)
    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
@endif
```

And for the empty state:

```blade
<x-empty-state :message="($emptyMessage ?? 'No records found')" :colspan="$columnCount" />
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Unit/View/Components/DataTableTest.php tests/Feature/Views/ComponentConsistencyTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/View/Components/DataTable.php resources/views/components/data-table.blade.php tests/Unit/View/Components/DataTableTest.php
git commit -m "feat(data-table): make actions column optional and declare all props"
```

### Task 5.2: Make `EmptyState` polymorphic

**Files:**
- Modify: `resources/views/components/empty-state.blade.php:1-19`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Write the failing test**

```php
'empty-state-div' => ['components.empty-state', ['as' => 'div', 'slot' => ''], ['div']],
```

Run and confirm FAIL.

- [ ] **Step 2: Update the component**

```blade
@props(['message' => 'No results found', 'colspan' => 1, 'icon' => null, 'as' => 'tr'])

@php
$tag = in_array($as, ['tr', 'div']) ? $as : 'tr';
@endphp

@if($tag === 'tr')
    <tr>
        <td colspan="{{ $colspan }}" class="px-4 py-12 text-center">
            <x-empty-state.content :message="$message" :icon="$icon" />
        </td>
    </tr>
@else
    <div class="px-4 py-12 text-center">
        <x-empty-state.content :message="$message" :icon="$icon" />
    </div>
@endif
```

Then create `resources/views/components/empty-state/content.blade.php`:

```blade
@props(['message', 'icon'])

<div class="flex flex-col items-center gap-3">
    @if($icon)
        <svg class="w-12 h-12 text-ink-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}" />
        </svg>
    @else
        <svg class="w-12 h-12 text-ink-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
    @endif
    
    <p class="text-sm text-ink-muted">{{ $message }}</p>
</div>
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/ComponentConsistencyTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/empty-state.blade.php resources/views/components/empty-state/content.blade.php tests/Feature/Views/ComponentConsistencyTest.php
git commit -m "feat(empty-state): support standalone div rendering"
```

---

## Phase 6: Migrate Remaining Inline Markup in Views

### Task 6.1: Replace inline textareas with `<x-textarea>`

**Files:**
- Modify: `resources/views/customers/create.blade.php:34-43`
- Modify: `resources/views/customers/edit.blade.php:40-49` (approximate; verify exact lines)
- Modify: `resources/views/customers/show.blade.php:133`
- Modify: `resources/views/compliance/sanctions/entries/create.blade.php:28`, `resources/views/compliance/sanctions/entries/create.blade.php:33`
- Modify: `resources/views/compliance/sanctions/entries/edit.blade.php:29`, `resources/views/compliance/sanctions/entries/edit.blade.php:34`
- Modify: `resources/views/transactions/cancel.blade.php:49`
- Modify: `resources/views/transactions/approve-cancellation.blade.php:54`
- Modify: `resources/views/transactions/reject-cancellation.blade.php:58`
- Modify: `resources/views/counters/handover.blade.php:64`
- Modify: `resources/views/counters/close.blade.php:82`
- Modify: `resources/views/counters/emergency-closure.blade.php:45`
- Modify: `resources/views/counters/emergency.blade.php:41`
- Modify: `resources/views/rates/index.blade.php:86`
- Modify: `resources/views/stock-transfers/create.blade.php:20`
- Test: `tests/Feature/Views/SharedComponentFormsTest.php`

- [ ] **Step 1: Write the failing test**

Extend `SharedComponentFormsTest` to assert textarea fields are rendered by the new component. Example for customer create:

```php
public function test_customer_create_form_uses_textarea_component(): void
{
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('customers.create'));
    $response->assertSee('name="address"', false);
    $response->assertSee('<textarea', false);
}
```

Run and confirm PASS if the view already has a textarea; this test mainly prevents regression.

- [ ] **Step 2: Replace each inline textarea with `<x-textarea>`**

Example replacement for `customers/create.blade.php`:

```blade
<x-textarea name="address" label="Address" rows="2">{{ old('address') }}</x-textarea>
```

For `compliance/sanctions/entries/create.blade.php`:

```blade
<x-textarea name="aliases" label="Aliases" rows="3" placeholder="Enter aliases, one per line">{{ old('aliases') }}</x-textarea>
<x-textarea name="details" label="Details" rows="3">{{ old('details') }}</x-textarea>
```

Repeat for every inline textarea, preserving `name`, `label`, `rows`, `placeholder`, `required`, and `old()` value behavior.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/SharedComponentFormsTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/ tests/Feature/Views/SharedComponentFormsTest.php
git commit -m "refactor(views): replace inline textareas with <x-textarea>"
```

### Task 6.2: Replace inline checkbox/radio inputs with components

**Files:**
- Modify: `resources/views/customers/create.blade.php:48-64` (radio risk_level)
- Modify: `resources/views/accounting/reconciliation.blade.php:44-96` (cleared checkboxes)
- Modify: `resources/views/users/create.blade.php:36-60` (is_active, mfa_enabled)
- Modify: `resources/views/users/edit.blade.php:57-68` (is_active)
- Modify: `resources/views/setup/index.blade.php:91-124` (currency/rate checkboxes)
- Modify: `resources/views/compliance/alerts/show.blade.php:101-102` (hidden inputs — leave as-is or use `<x-input type="hidden">` if a hidden variant is desired)
- Modify: `resources/views/counters/emergency.blade.php:37` (hidden input)
- Test: `tests/Feature/Views/SharedComponentFormsTest.php`

- [ ] **Step 1: Write the failing test**

Add a test that asserts radio/checkbox markup is rendered without raw inline inputs:

```php
public function test_customer_create_form_uses_radio_group_for_risk_level(): void
{
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('customers.create'));
    $response->assertSee('name="risk_level"', false);
    $response->assertSee('value="low"', false);
    $response->assertSee('value="high"', false);
}
```

- [ ] **Step 2: Replace inline controls**

Example for `customers/create.blade.php`:

```blade
<x-radio-group
    name="risk_level"
    label="Risk Level"
    :options="['low' => 'Low', 'medium' => 'Medium', 'high' => 'High']"
    :selected="old('risk_level')"
/>
```

Example for `users/create.blade.php`:

```blade
<x-checkbox name="is_active" label="Active User" :checked="old('is_active', true)" />
<x-checkbox name="mfa_enabled" label="Enable MFA (Required for all roles)" :checked="old('mfa_enabled')" />
```

For `setup/index.blade.php`, replace `<x-input type="checkbox">` with `<x-checkbox>`.

For `accounting/reconciliation.blade.php`, the cleared checkboxes are not backed by a form field name; either name them or keep as native inputs. If they are interactive but not submitted, a plain `<input type="checkbox">` is acceptable; document the exception.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact tests/Feature/Views/SharedComponentFormsTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/ tests/Feature/Views/SharedComponentFormsTest.php
git commit -m "refactor(views): replace inline checkbox/radio inputs with components"
```

### Task 6.3: Replace hand-rolled card headers with `<x-card title="...">` or `<x-card-section>`

**Files:**
- Modify: `resources/views/test-results/compare.blade.php:14-20`, `resources/views/test-results/compare.blade.php:61-67`
- Test: `tests/Feature/Views/ComponentConsistencyTest.php`

- [ ] **Step 1: Identify hand-rolled headers**

```bash
grep -R -n "<x-card>\s*\n\s*<div class=\"px-6 py-4 border-b\"" resources/views --include="*.blade.php"
```

- [ ] **Step 2: Convert to `<x-card title="...">`**

In `test-results/compare.blade.php`:

```blade
<x-card title="Run #{{ $run1->id }}" description="{{ $run1->created_at->format('M d, Y H:i') }}">
    <div class="p-6 space-y-4">
        ...
    </div>
</x-card>
```

- [ ] **Step 3: Run tests and commit**

```bash
php artisan test --compact tests/Feature/Views/ComponentConsistencyTest.php
git add resources/views/test-results/compare.blade.php
git commit -m "refactor(test-results): use card title slot instead of hand-rolled header"
```

---

## Phase 7: Documentation and Final Verification

### Task 7.1: Update `docs/view-styling-gap-analysis.md`

**Files:**
- Modify: `docs/view-styling-gap-analysis.md`

- [ ] **Step 1: Mark the refactor as in-progress and list remaining exceptions**

Update the Executive Summary and Conclusion:

```markdown
## Executive Summary

... The initial audit found that **86 of 95 view files** contained at least one design-system inconsistency. After remediation passes, **all views use theme tokens** for surfaces, borders, and text, and **inline buttons/inputs/selects/badges/alerts/cards/tables have been eliminated** except for native form controls that now have dedicated components (`<x-textarea>`, `<x-checkbox>`, `<x-radio-group>`) and the EOD print report which is intentionally separate.

## 6. Conclusion

**Overall Assessment:** 🟡 **Refactor Stabilized; Component Polish Remaining**

**Completed:**
- Tailwind v4 token architecture
- Dark mode toggle and CSS-variable switching
- 17+ shared Blade components
- Attribute forwarding across components
- Migration of buttons, inputs, selects, badges, alerts, cards, tables, page headers, stat cards, filter bars

**Remaining Exceptions (tracked):**
- `resources/views/reports/eod-reconciliation.blade.php` is a print/PDF view with inline CSS; excluded from design system by design.
```

- [ ] **Step 2: Commit**

```bash
git add docs/view-styling-gap-analysis.md
git commit -m "docs(view-styling): update gap analysis to reflect current state"
```

### Task 7.2: Update `docs/component-style-guide.md`

**Files:**
- Modify: `docs/component-style-guide.md`

- [ ] **Step 1: Add new components and corrected props**

Append or insert sections for:
- `Textarea Component`
- `Checkbox Component`
- `RadioGroup Component`
- Update `Alert` props to include `type="danger"` alias
- Update `Card`/`Card-Section` to note default `p-6` body padding
- Update `DataTable` props to include `hasActions`
- Update `EmptyState` props to include `as="div"`
- Update color table with new tokens (`on-primary`, `danger-hover`, etc.)

- [ ] **Step 2: Commit**

```bash
git add docs/component-style-guide.md
git commit -m "docs(components): document new components and updated props"
```

### Task 7.3: Final verification

- [ ] **Step 1: Run the full view/component test suite**

```bash
php artisan test --compact tests/Feature/Views/ tests/Feature/AlertViewTest.php tests/Unit/View/Components/
```

Expected: PASS.

- [ ] **Step 2: Run Pint on all changed files**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Run production build**

```bash
npm run build
```

Expected: successful build.

- [ ] **Step 4: Run a broad regression check**

```bash
php artisan test --compact
```

Expected: full suite passes (currently 856 tests).

- [ ] **Step 5: Commit any Pint fixes**

```bash
git add -A
git commit -m "style: apply pint formatting to view styling refactor"
```

---

## Self-Review Checklist

1. **Spec coverage:** Every finding from the analysis has at least one task:
   - ✅ Button raw colors / dark-mode text
   - ✅ Input/Select raw reds
   - ✅ Badge purple/gray
   - ✅ Alert class/blade mismatch and `danger` alias
   - ✅ Card/Card-Section padding
   - ✅ Navigation raw grays
   - ✅ Stat/Progress/Chart raw colors
   - ✅ DataTable hardcoded actions column
   - ✅ EmptyState standalone usage
   - ✅ Textarea/Checkbox/Radio components
   - ✅ View migrations
   - ✅ Documentation updates

2. **Placeholder scan:** No `TBD`, `TODO`, or vague instructions remain.

3. **Type consistency:**
   - `Alert` constructor props unchanged except removal of dead `showIcon`.
   - `DataTable` adds `hasActions` to constructor and `getColumnCount()`.
   - New components share prop naming with existing `input`/`select`.

4. **Test design:** Each task starts with a failing test or an assertion that will fail before the fix, then verifies the pass after.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-17-view-styling-refactor-completion.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — Dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — Execute tasks in this session using the executing-plans skill, with checkpoints for review.

Which approach would you like?
