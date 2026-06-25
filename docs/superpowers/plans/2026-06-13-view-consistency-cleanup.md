# View Consistency Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make all Blade views consistent in code syntax, shared layout usage, styling patterns, and component adoption; eliminate broken forms, placeholder content, and hardcoded dummy data.

**Architecture:** Incremental, view-by-view refactor. Fix invalid syntax and broken forms first, then standardize layouts, then replace dummy data, then roll out shared components. Each change is verified with targeted feature tests and `vendor/bin/pint`.

**Tech Stack:** Laravel 10, PHP 8.3.30, Blade, Tailwind CSS v4, PHPUnit

---

## File Structure

**Files to Modify (high-priority fixes):**
- `resources/views/components/*.blade.php` — fix invalid `@props` syntax
- `resources/views/compliance/sanctions/entries/create.blade.php` — add form action/method
- `resources/views/compliance/sanctions/entries/edit.blade.php` — add form action/method, bind model data
- `resources/views/customers/show.blade.php` — fix orphaned note form
- `resources/views/rates/index.blade.php` — wire rate override form
- `resources/views/compliance/risk-dashboard/trends.blade.php` — extend shared layout or implement charts
- `resources/views/compliance/risk-dashboard/customer.blade.php` — extend shared layout
- `resources/views/compliance/sanctions/entries/*.blade.php` — extend shared layout
- `resources/views/compliance/sanctions/import-logs/index.blade.php` — extend shared layout
- `resources/views/compliance/screening/show.blade.php` — extend shared layout
- `resources/views/compliance/unified/index.blade.php` — extend shared layout
- `resources/views/pages/mfa/recovery-codes.blade.php` — extend shared layout
- `resources/views/test-results/statistics.blade.php` — implement or remove chart placeholder

**Files to Modify (dummy-data replacement):**
- `resources/views/compliance/sanctions/entries/edit.blade.php`
- `resources/views/compliance/sanctions/entries/index.blade.php`
- `resources/views/compliance/sanctions/entries/show.blade.php`
- `resources/views/compliance/sanctions/index.blade.php`
- `resources/views/compliance/sanctions/show.blade.php`
- `resources/views/compliance/cases/index.blade.php`
- `resources/views/compliance/cases/show.blade.php`
- `resources/views/accounting/fiscal-years.blade.php`
- `resources/views/counters/acknowledge-handover.blade.php`

**Files to Create:**
- `tests/Feature/Views/ComponentSyntaxTest.php` — verify components render without syntax errors
- `tests/Feature/Views/SanctionsEntriesViewTest.php` — verify sanctions entry forms render with real data
- `tests/Feature/Views/LayoutConsistencyTest.php` — verify standalone pages extend shared layout
- `scripts/verify-view-consistency.sh` — post-implementation verification script

---

## Task 1: Fix Invalid `@props` Syntax in Components

**Files:**
- Modify: `resources/views/components/button.blade.php:1`
- Modify: `resources/views/components/page-header.blade.php:1`
- Modify: `resources/views/components/badge.blade.php:1`
- Modify: `resources/views/components/stat-card.blade.php:1`
- Modify: `resources/views/components/table.blade.php:1`
- Modify: `resources/views/components/card-section.blade.php:1`
- Modify: `resources/views/components/card.blade.php:1`
- Modify: `resources/views/components/chart-bar.blade.php:1`
- Modify: `resources/views/components/progress-bar.blade.php:1`
- Test: `tests/Feature/Views/ComponentSyntaxTest.php`

All components currently start with `<@props(...)` which is invalid Blade syntax. The leading `<` causes the directive to be output as literal text. Replace with `@props(...)` in every file.

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Views/ComponentSyntaxTest.php`:

```php
<?php

namespace Tests\Feature\Views;

use Tests\TestCase;

class ComponentSyntaxTest extends TestCase
{
    /**
     * @dataProvider componentProvider
     */
    public function test_component_renders_without_syntax_errors(string $component, array $data): void
    {
        $html = view($component, $data)->render();

        $this->assertStringNotContainsString('<@props', $html);
    }

    public static function componentProvider(): array
    {
        return [
            'button' => ['components.button', ['variant' => 'primary', 'slot' => 'Click']],
            'page-header' => ['components.page-header', ['title' => 'Page']],
            'badge' => ['components.badge', ['variant' => 'success', 'slot' => 'Active']],
            'stat-card' => ['components.stat-card', ['title' => 'Total', 'value' => '100']],
            'table' => ['components.table', []],
            'card-section' => ['components.card-section', []],
            'card' => ['components.card', []],
            'chart-bar' => ['components.chart-bar', ['value' => 75]],
            'progress-bar' => ['components.progress-bar', ['value' => 50]],
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Views/ComponentSyntaxTest.php`

Expected: FAIL — `assertStringNotContainsString` fails because rendered output contains `<@props`.

- [ ] **Step 3: Fix `button.blade.php`**

Current line 1:
```blade
<@props([
```

Replace with:
```blade
@props([
```

- [ ] **Step 4: Fix `page-header.blade.php`**

Current line 1:
```blade
<@props([
```

Replace with:
```blade
@props([
```

- [ ] **Step 5: Fix `badge.blade.php`**

Current line 1:
```blade
<@props([
```

Replace with:
```blade
@props([
```

- [ ] **Step 6: Fix `stat-card.blade.php`**

Current line 1:
```blade
<@props([
```

Replace with:
```blade
@props([
```

- [ ] **Step 7: Fix `table.blade.php`**

Current line 1:
```blade
<@props([
```

Replace with:
```blade
@props([
```

- [ ] **Step 8: Fix `card-section.blade.php`**

Current line 1:
```blade
<@props([
```

Replace with:
```blade
@props([
```

- [ ] **Step 9: Fix `card.blade.php`**

Current line 1:
```blade
<@props([
```

Replace with:
```blade
@props([
```

- [ ] **Step 10: Fix `chart-bar.blade.php`**

Current line 1:
```blade
<@props(['value' => 0, 'color' => null, 'minHeight' => 5])
```

Replace with:
```blade
@props(['value' => 0, 'color' => null, 'minHeight' => 5])
```

- [ ] **Step 11: Fix `progress-bar.blade.php`**

Current line 1:
```blade
<@props(['value' => 0, 'color' => null, 'max' => 100, 'size' => 'md', 'width' => 'w-20'])
```

Replace with:
```blade
@props(['value' => 0, 'color' => null, 'max' => 100, 'size' => 'md', 'width' => 'w-20'])
```

- [ ] **Step 12: Run test to verify it passes**

Run: `php artisan test tests/Feature/Views/ComponentSyntaxTest.php`

Expected: PASS

- [ ] **Step 13: Run Pint**

Run: `vendor/bin/pint --format agent`

Expected: No changes needed (Blade files are not PHP, but run to keep project clean).

- [ ] **Step 14: Commit**

```bash
git add resources/views/components/*.blade.php tests/Feature/Views/ComponentSyntaxTest.php
git commit -m "fix: correct invalid @props syntax in Blade components"
```

---

## Task 2: Wire Sanctions Entry Create Form

**Files:**
- Modify: `resources/views/compliance/sanctions/entries/create.blade.php:1-97`
- Modify: `routes/web.php:258-270` (sanctions route group)
- Modify: `app/Http/Controllers/Compliance/SanctionListController.php`
- Test: `tests/Feature/Views/SanctionsEntriesViewTest.php`

The create view is a standalone full-HTML page with a `<form>` that has no `action` or `method`. It should extend `<x-app-layout>` and post to a named route.

- [ ] **Step 1: Inspect existing sanctions routes and controller**

Run:
```bash
grep -n "sanctions" routes/web.php
php artisan route:list --name=compliance.sanctions
```

Expected: Route group exists under `compliance/sanctions` but entry CRUD routes may be missing.

- [ ] **Step 2: Add sanctions entry routes**

Read `routes/web.php` around the sanctions group (lines 258-270). Add entry resource routes inside the group:

```php
Route::prefix('compliance/sanctions')->name('compliance.sanctions.')->group(function () {
    // ... existing routes ...

    Route::prefix('entries')->name('entries.')->group(function () {
        Route::get('/', [SanctionListController::class, 'entriesIndex'])->name('index');
        Route::get('/create', [SanctionListController::class, 'entriesCreate'])->name('create');
        Route::post('/', [SanctionListController::class, 'entriesStore'])->name('store');
        Route::get('/{sanctionEntry}', [SanctionListController::class, 'entriesShow'])->name('show');
        Route::get('/{sanctionEntry}/edit', [SanctionListController::class, 'entriesEdit'])->name('edit');
        Route::put('/{sanctionEntry}', [SanctionListController::class, 'entriesUpdate'])->name('update');
        Route::delete('/{sanctionEntry}', [SanctionListController::class, 'entriesDestroy'])->name('destroy');
    });
});
```

- [ ] **Step 3: Add controller methods**

Read `app/Http/Controllers/Compliance/SanctionListController.php`. Add the CRUD methods needed for the new routes:

```php
public function entriesIndex(): \Illuminate\View\View
{
    return view('compliance.sanctions.entries.index', [
        'entries' => \App\Models\SanctionEntry::latest()->paginate(20),
    ]);
}

public function entriesCreate(): \Illuminate\View\View
{
    return view('compliance.sanctions.entries.create');
}

public function entriesStore(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
{
    $validated = $request->validate([
        'entity_name' => 'required|string|max:255',
        'list_source' => 'required|string|max:255',
        'entity_type' => 'required|string|max:255',
        'reference_number' => 'nullable|string|max:255',
        'nationality' => 'nullable|string|max:255',
        'date_listed' => 'nullable|date',
        'aliases' => 'nullable|string',
        'address' => 'nullable|string',
        'city' => 'nullable|string|max:255',
        'country' => 'nullable|string|max:255',
        'postal_code' => 'nullable|string|max:255',
        'additional_information' => 'nullable|string',
    ]);

    \App\Models\SanctionEntry::create($validated);

    return redirect()->route('compliance.sanctions.entries.index')
        ->with('success', 'Sanctions entry created.');
}
```

If `App\Models\SanctionEntry` does not exist, create a minimal Eloquent model and migration first (see Step 4).

- [ ] **Step 4: Verify or create SanctionEntry model**

Run:
```bash
ls app/Models/SanctionEntry.php
ls database/migrations/*sanction_entries* 2>/dev/null || echo "No migration found"
```

If missing, generate them:

```bash
php artisan make:model SanctionEntry --migration --no-interaction
```

Then edit the migration:

```php
Schema::create('sanction_entries', function (Blueprint $table) {
    $table->id();
    $table->string('entity_name');
    $table->string('list_source');
    $table->string('entity_type');
    $table->string('reference_number')->nullable();
    $table->string('nationality')->nullable();
    $table->date('date_listed')->nullable();
    $table->text('aliases')->nullable();
    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('country')->nullable();
    $table->string('postal_code')->nullable();
    $table->text('additional_information')->nullable();
    $table->timestamps();
});
```

And the model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SanctionEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_name',
        'list_source',
        'entity_type',
        'reference_number',
        'nationality',
        'date_listed',
        'aliases',
        'address',
        'city',
        'country',
        'postal_code',
        'additional_information',
    ];
}
```

- [ ] **Step 5: Rewrite create view to extend shared layout**

Replace the entire contents of `resources/views/compliance/sanctions/entries/create.blade.php` with:

```blade
<x-app-layout title="Create Sanctions Entry">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header title="Create Sanctions Entry">
            <x-slot:actions>
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('compliance.sanctions.entries.store') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-6 mt-8">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <x-input name="entity_name" label="Entity Name *" value="{{ old('entity_name') }}" required />
                <x-input name="reference_number" label="Reference Number" value="{{ old('reference_number') }}" />
                <x-select name="list_source" label="List Source *" :options="['ofac' => 'OFAC SDN', 'un' => 'UN Security Council', 'eu' => 'EU Sanctions List', 'bnm' => 'BNM List', 'other' => 'Other']" required />
                <x-select name="entity_type" label="Entity Type *" :options="['individual' => 'Individual', 'organization' => 'Organization', 'vessel' => 'Vessel', 'aircraft' => 'Aircraft']" required />
                <x-input name="nationality" label="Nationality" value="{{ old('nationality') }}" />
                <x-input type="date" name="date_listed" label="Date Listed" value="{{ old('date_listed') }}" />
            </div>

            <div class="mb-6">
                <label for="aliases" class="block text-xs font-medium text-gray-500 uppercase mb-1">Aliases</label>
                <textarea id="aliases" name="aliases" rows="3" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">{{ old('aliases') }}</textarea>
            </div>

            <div class="mb-6">
                <label for="address" class="block text-xs font-medium text-gray-500 uppercase mb-1">Address</label>
                <input type="text" id="address" name="address" value="{{ old('address') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg mb-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-input name="city" placeholder="City" value="{{ old('city') }}" />
                    <x-input name="country" placeholder="Country" value="{{ old('country') }}" />
                    <x-input name="postal_code" placeholder="Postal Code" value="{{ old('postal_code') }}" />
                </div>
            </div>

            <div class="mb-6">
                <label for="additional_information" class="block text-xs font-medium text-gray-500 uppercase mb-1">Additional Information</label>
                <textarea id="additional_information" name="additional_information" rows="3" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">{{ old('additional_information') }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Entry</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Write feature test**

Create `tests/Feature/Views/SanctionsEntriesViewTest.php`:

```php
<?php

namespace Tests\Feature\Views;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctionsEntriesViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_uses_shared_layout_and_has_csrf(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('compliance.sanctions.entries.create'));

        $response->assertStatus(200);
        $response->assertViewIs('compliance.sanctions.entries.create');
        $response->assertSee('<x-app-layout', false);
        $response->assertSee('name="_token"', false);
        $response->assertSee('action="' . e(route('compliance.sanctions.entries.store')) . '"', false);
    }

    public function test_store_creates_sanction_entry(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('compliance.sanctions.entries.store'), [
            'entity_name' => 'ACME Corp',
            'list_source' => 'ofac',
            'entity_type' => 'organization',
        ]);

        $response->assertRedirect(route('compliance.sanctions.entries.index'));
        $this->assertDatabaseHas('sanction_entries', ['entity_name' => 'ACME Corp']);
    }
}
```

- [ ] **Step 7: Run migration and tests**

Run:
```bash
php artisan migrate --no-interaction
php artisan test tests/Feature/Views/SanctionsEntriesViewTest.php
```

Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add resources/views/compliance/sanctions/entries/create.blade.php routes/web.php app/Http/Controllers/Compliance/SanctionListController.php app/Models/SanctionEntry.php database/migrations/*_create_sanction_entries_table.php tests/Feature/Views/SanctionsEntriesViewTest.php
git commit -m "feat: wire sanctions entry create form and add model"
```

---

## Task 3: Wire Sanctions Entry Edit Form and Bind Model Data

**Files:**
- Modify: `resources/views/compliance/sanctions/entries/edit.blade.php:1-98`
- Modify: `app/Http/Controllers/Compliance/SanctionListController.php`
- Test: `tests/Feature/Views/SanctionsEntriesViewTest.php`

- [ ] **Step 1: Add edit/show/update controller methods**

In `app/Http/Controllers/Compliance/SanctionListController.php`:

```php
public function entriesShow(\App\Models\SanctionEntry $sanctionEntry): \Illuminate\View\View
{
    return view('compliance.sanctions.entries.show', compact('sanctionEntry'));
}

public function entriesEdit(\App\Models\SanctionEntry $sanctionEntry): \Illuminate\View\View
{
    return view('compliance.sanctions.entries.edit', compact('sanctionEntry'));
}

public function entriesUpdate(\Illuminate\Http\Request $request, \App\Models\SanctionEntry $sanctionEntry): \Illuminate\Http\RedirectResponse
{
    $validated = $request->validate([
        'entity_name' => 'required|string|max:255',
        'list_source' => 'required|string|max:255',
        'entity_type' => 'required|string|max:255',
        'reference_number' => 'nullable|string|max:255',
        'nationality' => 'nullable|string|max:255',
        'date_listed' => 'nullable|date',
        'aliases' => 'nullable|string',
        'address' => 'nullable|string',
        'city' => 'nullable|string|max:255',
        'country' => 'nullable|string|max:255',
        'postal_code' => 'nullable|string|max:255',
        'additional_information' => 'nullable|string',
    ]);

    $sanctionEntry->update($validated);

    return redirect()->route('compliance.sanctions.entries.show', $sanctionEntry)
        ->with('success', 'Sanctions entry updated.');
}
```

- [ ] **Step 2: Rewrite edit view**

Replace the entire contents of `resources/views/compliance/sanctions/entries/edit.blade.php` with:

```blade
<x-app-layout title="Edit Sanctions Entry">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-page-header title="Edit Sanctions Entry" :description="$sanctionEntry->reference_number">
            <x-slot:actions>
                <x-button href="{{ route('compliance.sanctions.entries.index') }}" variant="secondary">Cancel</x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('compliance.sanctions.entries.update', $sanctionEntry) }}" class="bg-white border border-[#e5e5e5] rounded-xl p-6 mt-8">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <x-input name="entity_name" label="Entity Name *" value="{{ old('entity_name', $sanctionEntry->entity_name) }}" required />
                <x-input name="reference_number" label="Reference Number" value="{{ old('reference_number', $sanctionEntry->reference_number) }}" />
                <x-select name="list_source" label="List Source *" :options="['ofac' => 'OFAC SDN', 'un' => 'UN Security Council', 'eu' => 'EU Sanctions List', 'bnm' => 'BNM List', 'other' => 'Other']" selected="{{ old('list_source', $sanctionEntry->list_source) }}" required />
                <x-select name="entity_type" label="Entity Type *" :options="['individual' => 'Individual', 'organization' => 'Organization', 'vessel' => 'Vessel', 'aircraft' => 'Aircraft']" selected="{{ old('entity_type', $sanctionEntry->entity_type) }}" required />
                <x-input name="nationality" label="Nationality" value="{{ old('nationality', $sanctionEntry->nationality) }}" />
                <x-input type="date" name="date_listed" label="Date Listed" value="{{ old('date_listed', $sanctionEntry->date_listed?->format('Y-m-d')) }}" />
            </div>

            <div class="mb-6">
                <label for="aliases" class="block text-xs font-medium text-gray-500 uppercase mb-1">Aliases</label>
                <textarea id="aliases" name="aliases" rows="3" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">{{ old('aliases', $sanctionEntry->aliases) }}</textarea>
            </div>

            <div class="mb-6">
                <label for="address" class="block text-xs font-medium text-gray-500 uppercase mb-1">Address</label>
                <input type="text" id="address" name="address" value="{{ old('address', $sanctionEntry->address) }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg mb-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-input name="city" placeholder="City" value="{{ old('city', $sanctionEntry->city) }}" />
                    <x-input name="country" placeholder="Country" value="{{ old('country', $sanctionEntry->country) }}" />
                    <x-input name="postal_code" placeholder="Postal Code" value="{{ old('postal_code', $sanctionEntry->postal_code) }}" />
                </div>
            </div>

            <div class="mb-6">
                <label for="additional_information" class="block text-xs font-medium text-gray-500 uppercase mb-1">Additional Information</label>
                <textarea id="additional_information" name="additional_information" rows="3" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">{{ old('additional_information', $sanctionEntry->additional_information) }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <x-button href="{{ route('compliance.sanctions.entries.show', $sanctionEntry) }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Changes</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Add test for edit form**

Append to `tests/Feature/Views/SanctionsEntriesViewTest.php`:

```php
public function test_edit_form_binds_model_data(): void
{
    $user = User::factory()->create();
    $entry = \App\Models\SanctionEntry::factory()->create([
        'entity_name' => 'ACME Corp',
        'list_source' => 'ofac',
        'entity_type' => 'organization',
    ]);

    $response = $this->actingAs($user)->get(route('compliance.sanctions.entries.edit', $entry));

    $response->assertStatus(200);
    $response->assertSee('value="ACME Corp"', false);
    $response->assertSee('action="' . e(route('compliance.sanctions.entries.update', $entry)) . '"', false);
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Feature/Views/SanctionsEntriesViewTest.php`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/compliance/sanctions/entries/edit.blade.php app/Http/Controllers/Compliance/SanctionListController.php tests/Feature/Views/SanctionsEntriesViewTest.php
git commit -m "feat: wire sanctions entry edit form and bind model data"
```

---

## Task 4: Replace Dummy Data in Sanctions Views

**Files:**
- Modify: `resources/views/compliance/sanctions/entries/index.blade.php`
- Modify: `resources/views/compliance/sanctions/entries/show.blade.php`
- Modify: `resources/views/compliance/sanctions/index.blade.php`
- Modify: `resources/views/compliance/sanctions/show.blade.php`
- Modify: `app/Http/Controllers/Compliance/SanctionListController.php`
- Test: `tests/Feature/Views/SanctionsEntriesViewTest.php`

- [ ] **Step 1: Update entries/index to use $entries**

Replace hardcoded rows in `resources/views/compliance/sanctions/entries/index.blade.php` with a loop over `$entries`. Keep the same markup structure but bind model attributes:

```blade
<tbody class="divide-y divide-gray-200">
    @forelse($entries as $entry)
        <tr>
            <td class="px-4 py-3 text-sm text-gray-900">{{ $entry->reference_number ?: 'N/A' }}</td>
            <td class="px-4 py-3 text-sm text-gray-900">{{ $entry->entity_name }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ strtoupper($entry->list_source) }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ ucfirst($entry->entity_type) }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ $entry->date_listed?->format('Y-m-d') ?? 'N/A' }}</td>
            <td class="px-4 py-3 text-sm">
                <a href="{{ route('compliance.sanctions.entries.edit', $entry) }}" class="text-blue-600 hover:text-blue-800">Edit</a>
                <a href="{{ route('compliance.sanctions.entries.show', $entry) }}" class="ml-3 text-gray-600 hover:text-gray-800">View</a>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No entries found</td>
        </tr>
    @endforelse
</tbody>
```

- [ ] **Step 2: Update entries/show to use $sanctionEntry**

Bind all displayed fields to `$sanctionEntry` attributes. Remove all hardcoded values such as `John Doe`, `OFAC-12345`, `123 Main Street`, `2024-01-01`.

- [ ] **Step 3: Update sanctions/index and sanctions/show**

If these views list screening results rather than editable entries, bind them to a `$matches` or `$screening` collection passed from the controller. Replace `John Doe (OFAC) - 92% match` with actual loop data.

- [ ] **Step 4: Add factory**

Create `database/factories/SanctionEntryFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SanctionEntryFactory extends Factory
{
    protected $model = \App\Models\SanctionEntry::class;

    public function definition(): array
    {
        return [
            'entity_name' => $this->faker->name(),
            'list_source' => $this->faker->randomElement(['ofac', 'un', 'eu', 'bnm', 'other']),
            'entity_type' => $this->faker->randomElement(['individual', 'organization', 'vessel', 'aircraft']),
            'reference_number' => strtoupper($this->faker->bothify('OFAC-#####')),
            'nationality' => $this->faker->country(),
            'date_listed' => $this->faker->date(),
            'aliases' => $this->faker->optional()->paragraph(),
            'address' => $this->faker->optional()->streetAddress(),
            'city' => $this->faker->optional()->city(),
            'country' => $this->faker->optional()->country(),
            'postal_code' => $this->faker->optional()->postcode(),
            'additional_information' => $this->faker->optional()->sentence(),
        ];
    }
}
```

- [ ] **Step 5: Add tests for dummy-data absence**

Append to `tests/Feature/Views/SanctionsEntriesViewTest.php`:

```php
public function test_entries_index_does_not_show_hardcoded_dummy_data(): void
{
    $user = User::factory()->create();
    \App\Models\SanctionEntry::factory()->count(3)->create();

    $response = $this->actingAs($user)->get(route('compliance.sanctions.entries.index'));

    $response->assertStatus(200);
    $response->assertDontSee('John Doe', false);
    $response->assertDontSee('OFAC-12345', false);
}
```

- [ ] **Step 6: Run tests**

Run: `php artisan test tests/Feature/Views/SanctionsEntriesViewTest.php`

Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add resources/views/compliance/sanctions/entries/index.blade.php resources/views/compliance/sanctions/entries/show.blade.php resources/views/compliance/sanctions/index.blade.php resources/views/compliance/sanctions/show.blade.php database/factories/SanctionEntryFactory.php tests/Feature/Views/SanctionsEntriesViewTest.php
git commit -m "fix: replace hardcoded dummy data in sanctions views with model bindings"
```

---

## Task 5: Replace Dummy Data in Compliance Cases and Fiscal Years

**Files:**
- Modify: `resources/views/compliance/cases/index.blade.php`
- Modify: `resources/views/compliance/cases/show.blade.php`
- Modify: `resources/views/accounting/fiscal-years.blade.php`
- Modify: relevant controllers
- Test: `tests/Feature/Views/LayoutConsistencyTest.php` (reuse for data binding checks)

- [ ] **Step 1: Bind cases/index to $cases**

Replace `Jane Doe` placeholder with `{{ $case->assignedTo?->name ?? 'Unassigned' }}`.

- [ ] **Step 2: Bind cases/show to $case**

Replace hardcoded date/assignee with model attributes.

- [ ] **Step 3: Bind fiscal-years to $fiscalYears**

Replace the hardcoded fiscal year table with a loop over `$fiscalYears`. Use `$fiscalYears->first()` for the active fiscal year card and periods summary.

- [ ] **Step 4: Add tests**

Add assertions that views do not contain known dummy strings.

- [ ] **Step 5: Commit**

```bash
git add resources/views/compliance/cases/index.blade.php resources/views/compliance/cases/show.blade.php resources/views/accounting/fiscal-years.blade.php
git commit -m "fix: replace hardcoded dummy data in compliance cases and fiscal years"
```

---

## Task 6: Standardize Layouts for Standalone Compliance Pages

**Files:**
- Modify: `resources/views/compliance/risk-dashboard/customer.blade.php`
- Modify: `resources/views/compliance/risk-dashboard/trends.blade.php`
- Modify: `resources/views/compliance/sanctions/entries/create.blade.php` (already done in Task 2)
- Modify: `resources/views/compliance/sanctions/entries/edit.blade.php` (already done in Task 3)
- Modify: `resources/views/compliance/sanctions/entries/index.blade.php`
- Modify: `resources/views/compliance/sanctions/entries/show.blade.php`
- Modify: `resources/views/compliance/sanctions/import-logs/index.blade.php`
- Modify: `resources/views/compliance/screening/show.blade.php`
- Modify: `resources/views/compliance/unified/index.blade.php`
- Modify: `resources/views/pages/mfa/recovery-codes.blade.php`
- Test: `tests/Feature/Views/LayoutConsistencyTest.php`

- [ ] **Step 1: Convert risk-dashboard/customer.blade.php**

Replace the full `<!DOCTYPE html>` wrapper with:

```blade
<x-app-layout title="Customer Risk Dashboard">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- existing content body --}}
    </div>
</x-app-layout>
```

- [ ] **Step 2: Convert risk-dashboard/trends.blade.php**

Keep the page body but wrap in `<x-app-layout title="Risk Trends">`. Remove `<head>`, `<body>`, and outer `<html>` tags.

- [ ] **Step 3: Convert sanctions/entries/index.blade.php and show.blade.php**

Wrap in `<x-app-layout title="...">` and remove standalone HTML boilerplate.

- [ ] **Step 4: Convert sanctions/import-logs/index.blade.php**

Wrap in `<x-app-layout title="Sanctions Import Logs">`.

- [ ] **Step 5: Convert screening/show.blade.php**

Wrap in `<x-app-layout title="Screening Result">`.

- [ ] **Step 6: Convert unified/index.blade.php**

Wrap in `<x-app-layout title="Unified Compliance View">`. Replace the hardcoded form action `/compliance/unified` with `route('compliance.unified.index')`.

- [ ] **Step 7: Convert pages/mfa/recovery-codes.blade.php**

Wrap in `<x-app-layout title="Recovery Codes">`. Remove standalone header/main boilerplate.

- [ ] **Step 8: Write layout consistency test**

Create `tests/Feature/Views/LayoutConsistencyTest.php`:

```php
<?php

namespace Tests\Feature\Views;

use App\Models\User;
use Tests\TestCase;

class LayoutConsistencyTest extends TestCase
{
    /**
     * @dataProvider sharedLayoutViewProvider
     */
    public function test_view_extends_shared_layout(string $route, string $view): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get($route);

        $response->assertStatus(200);
        $response->assertViewIs($view);
        $html = $response->getContent();
        $this->assertStringContainsString('<x-app-layout', $html);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
    }

    public static function sharedLayoutViewProvider(): array
    {
        return [
            'risk-dashboard-customer' => ['/compliance/risk-dashboard/customer', 'compliance.risk-dashboard.customer'],
            'risk-dashboard-trends' => ['/compliance/risk-dashboard/trends', 'compliance.risk-dashboard.trends'],
            'sanctions-entries-index' => ['/compliance/sanctions/entries', 'compliance.sanctions.entries.index'],
            'sanctions-import-logs' => ['/compliance/sanctions/import-logs', 'compliance.sanctions.import-logs.index'],
            'screening-show' => ['/compliance/screening/1', 'compliance.screening.show'],
            'unified-index' => ['/compliance/unified', 'compliance.unified.index'],
            'mfa-recovery-codes' => ['/mfa/recovery-codes', 'pages.mfa.recovery-codes'],
        ];
    }
}
```

Adjust route parameters for views that require IDs if needed (e.g., use a factory-created model).

- [ ] **Step 9: Run tests**

Run: `php artisan test tests/Feature/Views/LayoutConsistencyTest.php`

Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add resources/views/compliance/risk-dashboard/customer.blade.php resources/views/compliance/risk-dashboard/trends.blade.php resources/views/compliance/sanctions/entries/index.blade.php resources/views/compliance/sanctions/entries/show.blade.php resources/views/compliance/sanctions/import-logs/index.blade.php resources/views/compliance/screening/show.blade.php resources/views/compliance/unified/index.blade.php resources/views/pages/mfa/recovery-codes.blade.php tests/Feature/Views/LayoutConsistencyTest.php
git commit -m "refactor: standardize standalone compliance pages on x-app-layout"
```

---

## Task 7: Fix Remaining Broken Forms

**Files:**
- Modify: `resources/views/customers/show.blade.php`
- Modify: `resources/views/rates/index.blade.php`
- Test: relevant existing feature tests

- [ ] **Step 1: Fix customers/show note form**

Locate the note form around line 143. Change it to:

```blade
<form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mt-4">
    @csrf
    <label for="note" class="sr-only">Add a note</label>
    <textarea id="note" name="note" rows="2" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg mb-2" placeholder="Add a note..."></textarea>
    <x-button type="submit" variant="primary" size="sm">Add Note</x-button>
</form>
```

If `customers.notes.store` route does not exist, add it to `routes/web.php`:

```php
Route::post('/customers/{customer}/notes', [CustomerController::class, 'storeNote'])->name('customers.notes.store');
```

- [ ] **Step 2: Add CustomerController::storeNote**

In `app/Http/Controllers/CustomerController.php`:

```php
public function storeNote(\Illuminate\Http\Request $request, \App\Models\Customer $customer): \Illuminate\Http\RedirectResponse
{
    $validated = $request->validate([
        'note' => 'required|string|max:2000',
    ]);

    $customer->notes()->create([
        'note' => $validated['note'],
        'created_by' => auth()->id(),
    ]);

    return back()->with('success', 'Note added.');
}
```

If a `CustomerNote` model/relationship does not exist, create the model and migration.

- [ ] **Step 3: Fix rates override form**

In `resources/views/rates/index.blade.php`, add `method="POST"` and `action` to `#override-form`:

```blade
<form id="override-form" method="POST" action="{{ route('rates.override') }}" class="...">
    @csrf
    {{-- existing fields --}}
</form>
```

If `rates.override` route does not exist, add it:

```php
Route::post('/rates/override', [RateController::class, 'override'])->name('rates.override');
```

- [ ] **Step 4: Add tests**

Create targeted feature tests for each form submission.

- [ ] **Step 5: Commit**

```bash
git add resources/views/customers/show.blade.php resources/views/rates/index.blade.php routes/web.php app/Http/Controllers/CustomerController.php tests/Feature/Views/
git commit -m "fix: wire remaining broken forms in customer and rate views"
```

---

## Task 8: Implement or Remove Chart Placeholders

**Files:**
- Modify: `resources/views/compliance/risk-dashboard/trends.blade.php`
- Modify: `resources/views/test-results/statistics.blade.php`
- Modify: `app/Http/Controllers/Compliance/RiskDashboardController.php`
- Modify: `app/Http/Controllers/TestResultsController.php`

- [ ] **Step 1: Implement risk trends chart**

Pass trend data from the controller:

```php
public function trends(): \Illuminate\View\View
{
    return view('compliance.risk-dashboard.trends', [
        'highRiskTrend' => \App\Services\ComplianceService::highRiskCustomerTrend(),
        'alertVolumeTrend' => \App\Services\ComplianceService::alertVolumeTrend(),
    ]);
}
```

Then replace the placeholder chart markup with an SVG bar chart loop over `$highRiskTrend` and `$alertVolumeTrend`, reusing the existing CSS bar pattern from `test-results/statistics.blade.php`.

- [ ] **Step 2: Implement test statistics trend chart**

The view already has a CSS bar chart implementation at lines 44-66. Remove the `<!-- Pass Rate Trend Chart Placeholder -->` comment since the chart is functional.

- [ ] **Step 3: Commit**

```bash
git add resources/views/compliance/risk-dashboard/trends.blade.php resources/views/test-results/statistics.blade.php app/Http/Controllers/Compliance/RiskDashboardController.php
git commit -m "feat: implement chart placeholders in risk trends and test statistics"
```

---

## Task 9: Roll Out Shared Components to Heavy Offenders

**Files:**
- Modify: `resources/views/customers/create.blade.php`
- Modify: `resources/views/customers/edit.blade.php`
- Modify: `resources/views/users/create.blade.php`
- Modify: `resources/views/users/edit.blade.php`
- Modify: `resources/views/accounting/journal/create.blade.php`

- [ ] **Step 1: Migrate customers/create to x-input/x-button/x-card**

Replace raw `<input>` and `<button>` elements with `<x-input>` and `<x-button>`. Wrap sections in `<x-card>` where appropriate. Ensure every input has a `label` (built into `<x-input>`).

- [ ] **Step 2: Repeat for customers/edit**

Same pattern, binding to `$customer`.

- [ ] **Step 3: Repeat for users/create and users/edit**

Same pattern, binding to `$user`.

- [ ] **Step 4: Repeat for accounting/journal/create**

Use `<x-input>`, `<x-button>`, and `<x-card>` for journal line items.

- [ ] **Step 5: Run Pint and feature tests**

Run:
```bash
vendor/bin/pint --format agent
php artisan test tests/Feature/CustomerTest.php tests/Feature/UserTest.php tests/Feature/Accounting/JournalTest.php
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/customers/create.blade.php resources/views/customers/edit.blade.php resources/views/users/create.blade.php resources/views/users/edit.blade.php resources/views/accounting/journal/create.blade.php
git commit -m "refactor: adopt shared components in customer, user, and journal forms"
```

---

## Task 10: Final Verification

**Files:**
- Create: `scripts/verify-view-consistency.sh`
- All modified files

- [ ] **Step 1: Create verification script**

```bash
#!/bin/bash
set -e

echo "=== View Consistency Verification ==="

echo ""
echo "1. Checking for invalid @props syntax..."
if grep -R '<@props' resources/views/components/; then
    echo "✗ Invalid @props found"
    exit 1
else
    echo "✓ No invalid @props syntax"
fi

echo ""
echo "2. Checking for standalone compliance views that should use x-app-layout..."
STANDALONE=$(grep -l '<!DOCTYPE html>' resources/views/compliance/risk-dashboard/*.blade.php resources/views/compliance/sanctions/entries/*.blade.php resources/views/compliance/sanctions/import-logs/*.blade.php resources/views/compliance/screening/*.blade.php resources/views/compliance/unified/*.blade.php resources/views/pages/mfa/recovery-codes.blade.php 2>/dev/null || true)
if [ -n "$STANDALONE" ]; then
    echo "✗ Standalone pages still exist:"
    echo "$STANDALONE"
    exit 1
else
    echo "✓ All target views use shared layout"
fi

echo ""
echo "3. Checking for known dummy strings..."
DUMMY=$(grep -R "John Doe\|OFAC-12345\|ID-12345\|123 Main Street" resources/views/ || true)
if [ -n "$DUMMY" ]; then
    echo "✗ Dummy data still present:"
    echo "$DUMMY"
    exit 1
else
    echo "✓ No known dummy data strings"
fi

echo ""
echo "4. Running view tests..."
php artisan test tests/Feature/Views/

echo ""
echo "5. Running Pint..."
vendor/bin/pint --format agent

echo ""
echo "=== Verification Complete ==="
```

- [ ] **Step 2: Make script executable**

```bash
chmod +x scripts/verify-view-consistency.sh
```

- [ ] **Step 3: Run verification script**

```bash
./scripts/verify-view-consistency.sh
```

Expected: All checks pass.

- [ ] **Step 4: Run full test suite**

```bash
php artisan test --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add scripts/verify-view-consistency.sh
git commit -m "chore: add view consistency verification script"
```

---

## Summary

**Total Tasks:** 10
**Estimated Time:** 6-10 hours
**Risk Level:** MEDIUM (touches many views; high regression risk without tests)

**Priority Order:**
1. Task 1: Fix invalid `@props` syntax — P0 (currently broken components)
2. Task 2-4: Wire sanctions forms and replace dummy data — P0
3. Task 5-6: Replace remaining dummy data and standardize layouts — P1
4. Task 7: Fix remaining broken forms — P1
5. Task 8: Implement chart placeholders — P1
6. Task 9: Roll out shared components — P2
7. Task 10: Final verification — required

**Testing Strategy:**
- Each task includes targeted feature tests.
- Run `php artisan test tests/Feature/Views/` after each task group.
- Run `vendor/bin/pint --format agent` before every commit.
- Final verification with `./scripts/verify-view-consistency.sh`.

**Post-Implementation:**
1. Run full test suite: `php artisan test --compact`
2. Run Pint: `vendor/bin/pint --format agent`
3. Manual smoke test in browser for sanctions, risk dashboard, rates, and customer/user forms.
