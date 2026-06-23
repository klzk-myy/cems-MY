# Setup Merge Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Merge `/setup` and `/setup/wizard` into single `/setup` page with auto-resume

**Architecture:** Modify `SetupController::index()` to serve wizard content when incomplete, redirect `wizard()` to `index`. Update view to show steps 1-6 plus completion state.

**Tech Stack:** Laravel 10, Blade templates

---

## Task 1: Update SetupController::index()

**Files:**
- Modify: `app/Http/Controllers/SetupController.php:24-43`

- [ ] **Step 1: Replace index() method**

```php
public function index(Request $request)
{
    $isSetupComplete = $this->isSetupComplete();

    if ($isSetupComplete) {
        return view('setup.index', [
            'isSetupComplete' => true,
            'currentStep' => 7,
            'progress' => 100,
        ]);
    }

    $step = $request->get('step', $this->getCurrentStep());

    return view('setup.index', [
        'isSetupComplete' => false,
        'currentStep' => (int) $step,
        'progress' => $this->calculateProgress(),
        'currencies' => Currency::all(),
    ]);
}
```

- [ ] **Step 2: Run tests to verify**

Run: `php artisan test --filter=SetupTest`
Expected: All tests pass

---

## Task 2: Update SetupController::wizard() to redirect

**Files:**
- Modify: `app/Http/Controllers/SetupController.php:34-43`

- [ ] **Step 1: Replace wizard() method**

```php
public function wizard(Request $request)
{
    $step = $request->get('step', $this->getCurrentStep());

    return redirect()->route('setup.index', ['step' => $step]);
}
```

---

## Task 3: Add redirect route for /setup/wizard

**Files:**
- Modify: `routes/web.php:101-114`

- [ ] **Step 1: Add redirect route after setup prefix**

In the `setup` route group (line 101), add:

```php
Route::get('/wizard', function () {
    return redirect()->route('setup.index');
})->name('setup.wizard');
```

---

## Task 4: Update setup/index.blade.php

**Files:**
- Modify: `resources/views/setup/index.blade.php`

- [ ] **Step 1: Replace entire file with wizard template**

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            <div class="max-w-2xl mx-auto">
                @if($isSetupComplete)
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-8 text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-semibold text-[#171717] mb-2">Setup Complete</h1>
                    <p class="text-sm text-[#6b6b6b] mb-6">Your CEMS-MY system is ready to use</p>
                    <a href="{{ route('dashboard') }}" class="inline-block px-6 py-2.5 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Go to Dashboard</a>
                </div>
                @else
                <h1 class="text-2xl font-semibold text-[#171717] mb-2">Setup Wizard</h1>
                <p class="text-sm text-[#6b6b6b] mb-6">Step {{ $currentStep }} of 6</p>
                <div class="w-full bg-[#e5e5e5] rounded-full h-2 mb-8">
                    <div class="bg-[#0a0a0a] h-2 rounded-full" style="width: {{ ($currentStep / 6) * 100 }}%;"></div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    @switch($currentStep)
                        @case(1)
                        <h2 class="text-lg font-semibold text-[#171717] mb-4">Business Configuration</h2>
                        <p class="text-[#6b6b6b]">Configure your business settings</p>
                        @break
                        @case(2)
                        <h2 class="text-lg font-semibold text-[#171717] mb-4">Admin User</h2>
                        <p class="text-[#6b6b6b]">Create the first admin account</p>
                        @break
                        @case(3)
                        <h2 class="text-lg font-semibold text-[#171717] mb-4">Currencies</h2>
                        <p class="text-[#6b6b6b]">Set up supported currencies</p>
                        @break
                        @case(4)
                        <h2 class="text-lg font-semibold text-[#171717] mb-4">Exchange Rates</h2>
                        <p class="text-[#6b6b6b]">Configure exchange rates</p>
                        @break
                        @case(5)
                        <h2 class="text-lg font-semibold text-[#171717] mb-4">Initial Stock</h2>
                        <p class="text-[#6b6b6b]">Set up initial stock/cash</p>
                        @break
                        @case(6)
                        <h2 class="text-lg font-semibold text-[#171717] mb-4">Opening Balance</h2>
                        <p class="text-[#6b6b6b]">Configure opening balance</p>
                        @break
                    @endswitch
                </div>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
```

---

## Task 5: Delete setup/wizard.blade.php

**Files:**
- Delete: `resources/views/setup/wizard.blade.php`

- [ ] **Step 1: Delete the file**

Run: `rm resources/views/setup/wizard.blade.php`

---

## Task 6: Verify routes

- [ ] **Step 1: Verify route redirect works**

Run: `php artisan route:list --name=setup`
Expected: `/setup` and `/setup/wizard` (redirect) both listed

- [ ] **Step 2: Run tests**

Run: `php artisan test --filter=SetupTest`
Expected: All tests pass

---

## Summary

| Task | Action |
|------|--------|
| 1 | Update `index()` to show wizard when incomplete |
| 2 | Update `wizard()` to redirect to `index` |
| 3 | Add redirect route for `/setup/wizard` |
| 4 | Update `setup/index.blade.php` with wizard UI |
| 5 | Delete `setup/wizard.blade.php` |
| 6 | Verify routes and tests |

---

**Plan saved to:** `docs/superpowers/plans/2026-05-01-setup-merge-plan.md`

**Two execution options:**

**1. Subagent-Driven (recommended)** - Dispatch fresh subagent per task, review between tasks

**2. Inline Execution** - Execute tasks in this session using executing-plans skill

Which approach?