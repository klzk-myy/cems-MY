# Navigation Dropdown Menus Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add collapsible dropdown sub-menus to 6 navigation categories (Counters, Stock & Cash, Stock Transfers, Accounting, Reports, Compliance) with click and hover expand, role-based visibility, and active state highlighting.

**Architecture:** Single-file modification to `navigation.blade.php` using Alpine.js for collapse state. Sub-menus reset on each page load. Both click and hover expand the dropdowns.

**Tech Stack:** Alpine.js (already in project via Livewire), Tailwind CSS

---

## Task 1: Update navigation.blade.php with dropdown structure

**Files:**
- Modify: `resources/views/components/navigation.blade.php`

- [ ] **Step 1: Add Alpine.js data structure for dropdowns**

Replace the opening `<nav>` tag with Alpine.js initialization:

```php
<nav x-data="{ open: false }" class="bg-gray-900 text-white w-64 min-h-screen flex flex-col">
```

- [ ] **Step 2: Update Operations section with dropdowns (Counters, Stock & Cash, Stock Transfers)**

Replace the Operations section (lines 21-51) with:

```php
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Operations</li>

        <li>
            <a href="{{ route('transactions.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('transactions.*') ? 'bg-gray-800' : '' }}">
                Transactions
            </a>
        </li>

        <li>
            <a href="{{ route('customers.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('customers.*') ? 'bg-gray-800' : '' }}">
                Customers
            </a>
        </li>

        <!-- Counters Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('counters.*') ? 'bg-gray-800' : '' }}">
                <span>Counters</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <ul x-show="open" x-transition class="bg-gray-800">
                <li>
                    <a href="{{ route('counters.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.index') ? 'bg-gray-700' : '' }}">
                        Counter List
                    </a>
                </li>
                <li>
                    <a href="{{ route('counters.open') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.open') ? 'bg-gray-700' : '' }}">
                        Open Counter
                    </a>
                </li>
                <li>
                    <a href="{{ route('counters.close') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.close') ? 'bg-gray-700' : '' }}">
                        Close Counter
                    </a>
                </li>
                <li>
                    <a href="{{ route('counters.handover') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.handover') ? 'bg-gray-700' : '' }}">
                        Handover
                    </a>
                </li>
                <li>
                    <a href="{{ route('counters.history') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.history') ? 'bg-gray-700' : '' }}">
                        History
                    </a>
                </li>
                @can('role:manager')
                <li>
                    <a href="{{ route('counters.emergency') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.emergency') ? 'bg-gray-700' : '' }}">
                        Emergency Closure
                    </a>
                </li>
                @endcan
            </ul>
        </li>

        <!-- Stock & Cash Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('stock-cash.*') ? 'bg-gray-800' : '' }}">
                <span>Stock & Cash</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <ul x-show="open" x-transition class="bg-gray-800">
                <li>
                    <a href="{{ route('stock-cash.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-cash.index') ? 'bg-gray-700' : '' }}">
                        Overview
                    </a>
                </li>
                <li>
                    <a href="{{ route('stock-cash.position') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-cash.position') ? 'bg-gray-700' : '' }}">
                        Position
                    </a>
                </li>
                <li>
                    <a href="{{ route('stock-cash.reconciliation') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-cash.reconciliation') ? 'bg-gray-700' : '' }}">
                        Reconciliation
                    </a>
                </li>
                <li>
                    <a href="{{ route('stock-cash.till-report') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-cash.till-report') ? 'bg-gray-700' : '' }}">
                        Till Report
                    </a>
                </li>
            </ul>
        </li>

        <!-- Stock Transfers Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('stock-transfers.*') ? 'bg-gray-800' : '' }}">
                <span>Stock Transfers</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <ul x-show="open" x-transition class="bg-gray-800">
                <li>
                    <a href="{{ route('stock-transfers.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-transfers.index') ? 'bg-gray-700' : '' }}">
                        Overview
                    </a>
                </li>
                <li>
                    <a href="{{ route('stock-transfers.create') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-transfers.create') ? 'bg-gray-700' : '' }}">
                        Create Transfer
                    </a>
                </li>
            </ul>
        </li>
```

- [ ] **Step 3: Update Finance section with Accounting dropdown**

Replace the Finance section (lines 53-67) with:

```php
        @can('role:manager')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Finance</li>

        <!-- Accounting Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('accounting.*') ? 'bg-gray-800' : '' }}">
                <span>Accounting</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <ul x-show="open" x-transition class="bg-gray-800">
                <li>
                    <a href="{{ route('accounting.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.index') ? 'bg-gray-700' : '' }}">
                        Overview
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.journal') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.journal') ? 'bg-gray-700' : '' }}">
                        Journal
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.trial-balance') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.trial-balance') ? 'bg-gray-700' : '' }}">
                        Trial Balance
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.profit-loss') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.profit-loss') ? 'bg-gray-700' : '' }}">
                        P&L Statement
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.balance-sheet') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.balance-sheet') ? 'bg-gray-700' : '' }}">
                        Balance Sheet
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.cash-flow') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.cash-flow') ? 'bg-gray-700' : '' }}">
                        Cash Flow
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.ratios') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.ratios') ? 'bg-gray-700' : '' }}">
                        Financial Ratios
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.budget') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.budget') ? 'bg-gray-700' : '' }}">
                        Budget
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.revaluation') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.revaluation') ? 'bg-gray-700' : '' }}">
                        Revaluation
                    </a>
                </li>
                <li>
                    <a href="{{ route('accounting.reconciliation') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.reconciliation') ? 'bg-gray-700' : '' }}">
                        Reconciliation
                    </a>
                </li>
            </ul>
        </li>

        <li>
            <a href="{{ route('rates.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('rates.*') ? 'bg-gray-800' : '' }}">
                Rates
            </a>
        </li>
        @endcan
```

- [ ] **Step 4: Update Compliance section with dropdown**

Replace the Compliance section (lines 69-95) with:

```php
        @can('role:compliance')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Compliance</li>

        <!-- Compliance Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance') ? 'bg-gray-800' : '' }}">
                <span>Compliance Dashboard</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <ul x-show="open" x-transition class="bg-gray-800">
                <li>
                    <a href="{{ route('compliance') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance') && !request()->routeIs('compliance.*') ? 'bg-gray-700' : '' }}">
                        Overview
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.alerts.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.alerts.*') ? 'bg-gray-700' : '' }}">
                        Alerts
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.cases.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.cases.*') ? 'bg-gray-700' : '' }}">
                        Cases
                    </a>
                </li>
                <li>
                    <a href="{{ route('str.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('str.*') ? 'bg-gray-700' : '' }}">
                        STR
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.ctos.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.ctos.*') ? 'bg-gray-700' : '' }}">
                        CTOS Reports
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.edd-templates.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.edd-templates.*') ? 'bg-gray-700' : '' }}">
                        EDD Templates
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.findings.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.findings.*') ? 'bg-gray-700' : '' }}">
                        Findings
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.screening.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.screening.*') ? 'bg-gray-700' : '' }}">
                        Screening
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.workspace.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.workspace.*') ? 'bg-gray-700' : '' }}">
                        Workspace
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.unified.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.unified.*') ? 'bg-gray-700' : '' }}">
                        Unified View
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.risk-dashboard.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.risk-dashboard.*') ? 'bg-gray-700' : '' }}">
                        Risk Dashboard
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.sanctions.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.sanctions.*') ? 'bg-gray-700' : '' }}">
                        Sanctions
                    </a>
                </li>
                <li>
                    <a href="{{ route('compliance.reporting.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.reporting.*') ? 'bg-gray-700' : '' }}">
                        Reporting
                    </a>
                </li>
            </ul>
        </li>
        @endcan
```

- [ ] **Step 5: Update Reports section with dropdown**

Replace the Reports section (lines 97-105) with:

```php
        @can('role:manager')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Reports</li>

        <!-- Reports Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('reports.*') ? 'bg-gray-800' : '' }}">
                <span>Reports</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <ul x-show="open" x-transition class="bg-gray-800">
                <li>
                    <a href="{{ route('reports.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.index') ? 'bg-gray-700' : '' }}">
                        Overview
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.lctr') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.lctr') ? 'bg-gray-700' : '' }}">
                        LCTR
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.lmca') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.lmca') ? 'bg-gray-700' : '' }}">
                        LMCA
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.msb2') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.msb2') ? 'bg-gray-700' : '' }}">
                        MSB2
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.position-limit') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.position-limit') ? 'bg-gray-700' : '' }}">
                        Position Limit
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.profitability') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.profitability') ? 'bg-gray-700' : '' }}">
                        Profitability
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.customer-analysis') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.customer-analysis') ? 'bg-gray-700' : '' }}">
                        Customer Analysis
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.compliance-summary') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.compliance-summary') ? 'bg-gray-700' : '' }}">
                        Compliance Summary
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.quarterly-lvr') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.quarterly-lvr') ? 'bg-gray-700' : '' }}">
                        Quarterly LVR
                    </a>
                </li>
            </ul>
        </li>
        @endcan
```

- [ ] **Step 6: Update System section with Users dropdown**

Replace the Users section (lines 107-114) with:

```php
        @can('role:admin')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">System</li>

        <!-- Users Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('users.*') ? 'bg-gray-800' : '' }}">
                <span>Users</span>
                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <ul x-show="open" x-transition class="bg-gray-800">
                <li>
                    <a href="{{ route('users.index') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('users.index') ? 'bg-gray-700' : '' }}">
                        User List
                    </a>
                </li>
                <li>
                    <a href="{{ route('users.create') }}" class="flex items-center pl-8 pr-4 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('users.create') ? 'bg-gray-700' : '' }}">
                        Create User
                    </a>
                </li>
            </ul>
        </li>
        @endcan
```

- [ ] **Step 7: Add CSS transition class to head**

Add to `resources/css/app.css`:
```css
/* Navigation dropdown transitions */
.rotate-180 {
    transform: rotate(180deg);
}
```

- [ ] **Step 8: Verify routes exist**

Run: `php artisan route:list --name=counters --name=stock-cash --name=stock-transfers --name=accounting --name=compliance --name=reports --name=users 2>/dev/null | head -60`

- [ ] **Step 9: Test the navigation**

Run: `php artisan route:list 2>/dev/null | grep -E "(counters|stock-cash|stock-transfers|accounting|compliance|reporting|users)\." | wc -l`

Expected: Count should show all routes are registered

- [ ] **Step 10: Commit**

```bash
git add resources/views/components/navigation.blade.php resources/css/app.css
git commit -m "feat: add dropdown sub-menus to navigation

Add collapsible dropdown menus to 6 navigation categories:
- Operations: Counters, Stock & Cash, Stock Transfers
- Finance: Accounting (Manager only)
- Compliance: Full sub-menu (Compliance Officer only)
- Reports: Full sub-menu (Manager only)
- System: Users (Admin only)

Features:
- Click to expand/collapse dropdowns
- Hover to expand dropdowns
- Active route highlighting in main menu AND sub-menu
- Chevron rotation animation on expand
- Role-based gate checks for sub-items
- State resets on page load (no persistence)
```

---

## Self-Review Checklist

- [ ] All 6 categories have dropdown structure: Counters, Stock & Cash, Stock Transfers, Accounting, Compliance, Reports
- [ ] Dropdowns use `x-data="{ open: false }"` with `@click`, `@mouseenter`, `@mouseleave`
- [ ] Sub-menus use `x-show="open"` with `x-transition` for animation
- [ ] Chevron uses `:class="open ? 'rotate-180' : ''"` with CSS transform
- [ ] Sub-menu items use `pl-8` for indentation
- [ ] Active state uses `{{ request()->routeIs('route.name') ? 'bg-gray-700' : '' }}`
- [ ] Role gates `@can('role:...')` applied to sub-items where appropriate
- [ ] No placeholders - all route names are concrete
- [ ] `rotate-180` class added to CSS for chevron animation