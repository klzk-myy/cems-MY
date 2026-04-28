# Livewire Codebase Standardization Plan

**Date:** 2026-04-29
**Scope:** Full codebase (89 components, 99 views, 242 routes)
**Approach:** Incremental migration with rollback capability

## Overview and Goals

**Objective:** Create a comprehensive standardization plan for the CEMS-MY Livewire codebase that can be applied incrementally without breaking existing functionality.

**Current State:**
- 89 Livewire components across 18 directories
- 99 Blade views (46 full HTML, 53 using layouts)
- 242 routes with inconsistent patterns
- Mixed middleware, naming, and layout approaches

**Standardization Goals:**
1. **Consistency:** Unified patterns across routes, views, and components
2. **Maintainability:** Clear conventions that make the codebase easier to understand and modify
3. **Performance:** Optimized view rendering and component loading
4. **Safety:** Incremental migration with rollback capability

**Success Criteria:**
- All routes follow consistent naming and middleware patterns
- All views use a unified layout approach
- All components follow consistent property naming and validation patterns
- Zero breaking changes during migration
- Test coverage maintained throughout

## Route Standardization

**Current Issues:**
- Inconsistent middleware patterns (`role:manager` vs `role:compliance` vs `role:admin`)
- Mixed naming conventions (`compliance.alerts` vs `compliance/alerts`)
- Duplicate route groups (STR has both compliance and manager groups)
- 242 routes across multiple nested groups

**Standardization Rules:**

**1. Middleware Pattern:**
```php
// ✅ Standard: Use role-based middleware at group level
Route::middleware(['auth', 'role:manager'])->prefix('accounting')->name('accounting.')->group(function () {
    // routes
});

// ❌ Avoid: Inline middleware on individual routes
Route::get('/accounting', AccountingIndex::class)->middleware('role:manager');
```

**2. Naming Convention:**
```php
// ✅ Standard: Use kebab-case for prefixes, dot notation for names
Route::prefix('compliance/alerts')->name('compliance.alerts.')->group(function () {
    Route::get('/', AlertsIndex::class)->name('index');
});

// ❌ Avoid: Mixed conventions
Route::prefix('complianceAlerts')->name('compliance_alerts.')->group(function () {
```

**3. Route Organization:**
```php
// ✅ Standard: Group by functional area with clear hierarchy
Route::middleware(['auth', 'role:compliance'])->group(function () {
    // Compliance routes
    Route::prefix('compliance')->name('compliance.')->group(function () {
        Route::get('/', Dashboard::class)->name('index');
        Route::prefix('alerts')->name('alerts.')->group(function () {
            Route::get('/', AlertsIndex::class)->name('index');
        });
    });
});
```

**Migration Steps:**
1. Audit all routes and identify inconsistencies
2. Create route mapping document (old → new)
3. Update routes in batches (10-15 routes per batch)
4. Test each batch before proceeding
5. Update tests to use new route names

## View Layout Consistency

**Current Issues:**
- 46 views use full HTML structure (`<!DOCTYPE html>`)
- 53 views use Blade layouts (`@extends`)
- Inconsistent layout usage across components
- Some components duplicate sidebar/navigation

**Standardization Rules:**

**1. Layout Approach:**
```php
// ✅ Standard: All Livewire views use Blade layouts
// resources/views/livewire/transactions/index.blade.php
@extends('livewire.layout.app-shell')

@section('content')
    {{-- Component content --}}
@endsection

// ❌ Avoid: Full HTML structure in Livewire views
<!DOCTYPE html>
<html>
<head>...</head>
<body>
    {{-- Content --}}
</body>
</html>
```

**2. Layout Structure:**
```php
// ✅ Standard: Use app-shell layout with sidebar
// resources/views/livewire/layout/app-shell.blade.php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'CEMS-MY' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        @include('livewire.layout.sidebar')
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            @yield('content')
        </main>
    </div>
</body>
</html>

// resources/views/livewire/layout/sidebar.blade.php
<aside class="w-60 bg-white border-r border-[#e5e5e5] flex flex-col shrink-0">
    {{-- Sidebar content --}}
</aside>
```

**3. Component View Pattern:**
```php
// ✅ Standard: Minimal component views
// resources/views/livewire/transactions/index.blade.php
@extends('livewire.layout.app-shell')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-[#171717]">Transactions</h1>
            <a href="{{ route('transactions.create') }}" 
               class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">
                New Transaction
            </a>
        </div>
        
        {{-- Component-specific content --}}
        @if($transactions->count() > 0)
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    {{-- Table content --}}
                </table>
            </div>
        @else
            <div class="text-center py-12 text-[#6b6b6b]">
                No transactions found
            </div>
        @endif
    </div>
@endsection
```

**Migration Steps:**
1. Create unified `livewire.layout.app-shell` layout
2. Create `livewire.layout.sidebar` component
3. Migrate full-HTML views to use layouts (10-15 views per batch)
4. Test each batch for visual consistency
5. Remove duplicate sidebar/navigation code from views

## Component Pattern Standardization

**Current Issues:**
- Inconsistent property naming (`$search` vs `$searchTerm`)
- Mixed validation approaches (attributes vs rules method)
- Some components have business logic that should be in services
- Inconsistent use of `mount()` vs constructor initialization

**Standardization Rules:**

**1. Property Naming:**
```php
// ✅ Standard: Descriptive, camelCase property names
class TransactionIndex extends BaseComponent
{
    public string $search = '';
    public ?string $type = '';
    public ?string $status = '';
    public ?string $dateFrom = '';
    public ?string $dateTo = '';
}

// ❌ Avoid: Inconsistent or abbreviated names
class TransactionIndex extends BaseComponent
{
    public string $s = '';
    public ?string $t = '';
    public ?string $st = '';
}
```

**2. Validation Approach:**
```php
// ✅ Standard: Use Livewire attributes for validation
use Livewire\Attributes\Validate;

class TransactionCreate extends BaseComponent
{
    #[Validate('required')]
    public string $type = '';
    
    #[Validate('required')]
    public string $currencyCode = '';
    
    #[Validate('required', 'numeric')]
    public string $amount = '';
    
    #[Validate('required')]
    public int $customerId = 0;
}

// ❌ Avoid: Rules method (harder to maintain)
class TransactionCreate extends BaseComponent
{
    protected $rules = [
        'type' => 'required',
        'currencyCode' => 'required',
        'amount' => 'required|numeric',
        'customerId' => 'required|integer',
    ];
}
```

**3. Initialization Pattern:**
```php
// ✅ Standard: Use mount() for initialization
class TransactionIndex extends BaseComponent
{
    public string $search = '';
    
    public function mount(): void
    {
        $this->loadInitialData();
    }
    
    protected function loadInitialData(): void
    {
        // Load initial data
    }
}

// ❌ Avoid: Constructor for initialization (breaks Livewire lifecycle)
class TransactionIndex extends BaseComponent
{
    public function __construct()
    {
        $this->loadInitialData();
    }
}
```

**4. Service Injection:**
```php
// ✅ Standard: Use constructor injection for services
class TransactionCreate extends BaseComponent
{
    public function __construct(
        protected TransactionService $transactionService,
        protected RateManagementService $rateService,
    ) {}
    
    public function create(): void
    {
        $this->transactionService->createTransaction(/* ... */);
    }
}

// ❌ Avoid: Service locator pattern
class TransactionCreate extends BaseComponent
{
    public function create(): void
    {
        $service = app(TransactionService::class);
        $service->createTransaction(/* ... */);
    }
}
```

**5. Business Logic Separation:**
```php
// ✅ Standard: Keep business logic in services
class TransactionIndex extends BaseComponent
{
    public function __construct(
        protected TransactionService $transactionService,
    ) {}
    
    protected function getTransactions(): LengthAwarePaginator
    {
        return $this->transactionService->getFilteredTransactions(
            $this->search,
            $this->type,
            $this->status,
            $this->dateFrom,
            $this->dateTo,
        );
    }
}

// ❌ Avoid: Business logic in component
class TransactionIndex extends BaseComponent
{
    protected function getTransactions(): LengthAwarePaginator
    {
        $query = Transaction::query();
        
        if (!empty($this->search)) {
            $query->where('id', 'like', "%{$this->search}%");
        }
        
        // ... more business logic
    }
}
```

**Migration Steps:**
1. Create component pattern documentation
2. Audit all components for inconsistencies
3. Update components in batches (5-10 components per batch)
4. Extract business logic to services where needed
5. Test each batch for functionality

## Testing & Validation

**Current State:**
- Limited component test coverage
- No route tests for Livewire routes
- No view tests for layout consistency

**Testing Strategy:**

**1. Component Tests:**
```php
// ✅ Standard: Test component behavior
class TransactionIndexTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_component_renders_with_transactions()
    {
        $transactions = Transaction::factory()->count(5)->create();
        
        Livewire::test(TransactionIndex::class)
            ->assertSee($transactions->first()->id)
            ->assertSee('Transactions');
    }
    
    public function test_search_filters_transactions()
    {
        $transaction = Transaction::factory()->create(['id' => 'TXN-001']);
        
        Livewire::test(TransactionIndex::class)
            ->set('search', 'TXN-001')
            ->assertSee('TXN-001');
    }
}
```

**2. Route Tests:**
```php
// ✅ Standard: Test route accessibility
class RouteAccessibilityTest extends TestCase
{
    public function test_transactions_index_accessible_by_authenticated_users()
    {
        $user = User::factory()->create(['role' => UserRole::Teller]);
        
        $response = $this->actingAs($user)->get(route('transactions.index'));
        $response->assertStatus(200);
    }
    
    public function test_compliance_routes_require_compliance_role()
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        
        $response = $this->actingAs($manager)->get(route('compliance.alerts.index'));
        $response->assertStatus(403);
    }
}
```

**3. View Tests:**
```php
// ✅ Standard: Test view structure
class ViewStructureTest extends TestCase
{
    public function test_livewire_views_use_app_shell_layout()
    {
        $views = glob(resource_path('views/livewire/**/*.blade.php'));
        
        foreach ($views as $view) {
            $content = file_get_contents($view);
            
            // Skip layout files themselves
            if (str_contains($view, 'layout/')) {
                continue;
            }
            
            $this->assertStringContainsString(
                "@extends('livewire.layout.app-shell')",
                $content,
                "View {$view} should use app-shell layout"
            );
        }
    }
}
```

**Validation Criteria:**
- All component tests pass
- All route tests pass
- All view structure tests pass
- No breaking changes detected
- Performance metrics maintained

## Implementation Timeline

**Phase 1: Route Standardization** (Week 1)
- Days 1-2: Audit routes and create mapping document
- Days 3-4: Update routes in batches (10-15 per batch)
- Day 5: Test and validate

**Phase 2: View Layout Consistency** (Week 2)
- Days 1-2: Create unified layouts
- Days 3-4: Migrate views to use layouts (10-15 per batch)
- Day 5: Test and validate

**Phase 3: Component Pattern Standardization** (Week 3)
- Days 1-2: Create component pattern documentation
- Days 3-4: Update components in batches (5-10 per batch)
- Day 5: Test and validate

**Phase 4: Testing & Validation** (Week 4)
- Days 1-2: Add component tests
- Days 3-4: Add route and view tests
- Day 5: Final validation and documentation

**Rollback Strategy:**
- Each phase is committed separately
- Git branches for each phase
- Ability to rollback any phase independently

## Risk Assessment

**Low Risk:**
- Route naming changes (backward compatible with aliases)
- View layout migration (visual only, no logic changes)

**Medium Risk:**
- Component pattern updates (may affect component behavior)
- Service extraction (requires careful testing)

**Mitigation:**
- Incremental batch processing
- Comprehensive testing at each step
- Git branches for rollback capability
- Feature flags for gradual rollout

## Success Metrics

**Quantitative:**
- 100% route naming consistency
- 100% view layout consistency
- 100% component pattern consistency
- 0 breaking changes
- Test coverage maintained at 80%+

**Qualitative:**
- Improved code maintainability
- Reduced cognitive load for developers
- Better performance through optimized views
- Clearer separation of concerns
