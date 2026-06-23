# Setup Merge Design

**Date:** 2026-05-01
**Goal:** Merge `/setup` and `/setup/wizard` into single `/setup` page

## Overview

Combine the setup status page (`/setup`) and multi-step wizard (`/setup/wizard`) into a single unified page at `/setup` that:
- Auto-resumes from the correct step when setup is incomplete
- Shows completion status with "Go to Dashboard" when setup is complete

## Routes

| Route | Action |
|-------|--------|
| `GET /setup` | Unified setup page (index controller) |
| `GET /setup/wizard` | Redirect to `/setup` |

## Controller Changes

### `SetupController::index()`

```php
public function index(Request $request)
{
    $isSetupComplete = $this->isSetupComplete();

    if ($isSetupComplete) {
        return view('setup.index', [
            'isSetupComplete' => true,
            'currentStep' => 7, // completion
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

### `SetupController::wizard()`

Replace body with redirect:

```php
public function wizard(Request $request)
{
    $step = $request->get('step', $this->getCurrentStep());

    return redirect()->route('setup.index', ['step' => $step]);
}
```

## Wizard Steps

| Step | Session Key | Title |
|------|-------------|-------|
| 1 | `setup.business` | Business Configuration |
| 2 | `setup.admin` | Admin User |
| 3 | `setup.currencies` | Currencies |
| 4 | `setup.rates` | Exchange Rates |
| 5 | `setup.stock` | Initial Stock |
| 6 | `setup.opening_balance` | Opening Balance |
| 7 | - | Complete |

## View (setup.index)

- Single blade template handles both states
- Shows step indicator (1-6) with progress bar
- Shows completion state when `isSetupComplete = true`
- Step content rendered via `@includeWhen` or `@switch`

## Delete

- `resources/views/setup/wizard.blade.php`

## Files to Modify

1. `routes/web.php` - Add redirect route for `/setup/wizard`
2. `app/Http/Controllers/SetupController.php` - Update `index()` and `wizard()`
3. `resources/views/setup/index.blade.php` - Update to handle wizard steps

## Files to Delete

1. `resources/views/setup/wizard.blade.php`