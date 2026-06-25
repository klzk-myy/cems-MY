# Sidebar Navigation Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace flat sidebar with collapsible dropdown navigation in base.blade.php

**Architecture:** Single task - replace the sidebar section (lines 46-365) in `layouts/base.blade.php` with new collapsible dropdown navigation using Alpine.js for interactivity.

**Tech Stack:** Alpine.js (already in project), Tailwind CSS v4

---

## Task 1: Replace Sidebar Navigation

**Files:**
- Modify: `resources/views/layouts/base.blade.php:46-365`

### Step 1: Replace sidebar navigation section

Replace the entire `<nav>` block (lines 46-365) with new collapsible dropdown sidebar:

```blade
{{-- Navigation --}}
<nav class="flex-1 overflow-y-auto px-3 pb-6" x-data="{ open: null }">

    {{-- Dashboard Section --}}
    <div class="nav-section">
        <x-sidebar-dropdown title="Dashboard" icon="home">
            <x-sidebar-link href="/dashboard" :active="request()->is('dashboard')">Dashboard</x-sidebar-link>
            <x-sidebar-link href="/performance" :active="request()->is('performance')">Performance</x-sidebar-link>
            <x-sidebar-link href="/rates" :active="request()->is('rates')">Exchange Rates</x-sidebar-link>
        </x-sidebar-dropdown>
    </div>

    {{-- Operations Section --}}
    <div class="nav-section">
        <x-sidebar-dropdown title="Operations" icon="cash">
            <x-sidebar-link href="/transactions" :active="request()->is('transactions')">Transactions</x-sidebar-link>
            <x-sidebar-link href="/transactions/create" :active="request()->is('transactions/create')">Create Transaction</x-sidebar-link>
            <x-sidebar-link href="/customers" :active="request()->is('customers')">Customers</x-sidebar-link>
            <x-sidebar-link href="/customers/create" :active="request()->is('customers/create')">Create Customer</x-sidebar-link>
        </x-sidebar-dropdown>
    </div>

    {{-- Counter Section --}}
    <div class="nav-section">
        <x-sidebar-dropdown title="Counter" icon="register">
            <x-sidebar-link href="/counters" :active="request()->is('counters*')">Counters</x-sidebar-link>
        </x-sidebar-dropdown>
    </div>

    {{-- Stock Section (Manager+) --}}
    @if(auth()->check() && auth()->user()->role->isManager())
    <div class="nav-section">
        <x-sidebar-dropdown title="Stock" icon="boxes">
            <x-sidebar-link href="/stock-cash" :active="request()->is('stock-cash*')">Stock & Cash</x-sidebar-link>
            <x-sidebar-link href="/stock-transfers" :active="request()->is('stock-transfers*')">Stock Transfers</x-sidebar-link>
        </x-sidebar-dropdown>
    </div>
    @endif

    {{-- Compliance Section (Compliance+) --}}
    @if(auth()->check() && (auth()->user()->role->isCompliance() || auth()->user()->role->isAdmin()))
    <div class="nav-section">
        <x-sidebar-dropdown title="Compliance" icon="shield">
            <x-sidebar-link href="/compliance" :active="request()->is('compliance') && !request()->is('compliance/*')">Dashboard</x-sidebar-link>
            <x-sidebar-link href="/compliance/alerts" :active="request()->is('compliance/alerts*')">Alert Triage</x-sidebar-link>
            <x-sidebar-link href="/compliance/cases" :active="request()->is('compliance/cases*')">Cases</x-sidebar-link>
            <x-sidebar-link href="/str" :active="request()->is('str*')">STR Reports</x-sidebar-link>
            <x-sidebar-link href="/compliance/edd" :active="request()->is('compliance/edd*')">EDD Records</x-sidebar-link>
            <x-sidebar-link href="/compliance/sanctions" :active="request()->is('compliance/sanctions*')">Sanctions</x-sidebar-link>
            <x-sidebar-link href="/compliance/risk-dashboard" :active="request()->is('compliance/risk-dashboard*')">Risk Dashboard</x-sidebar-link>
            <x-sidebar-link href="/compliance/reporting" :active="request()->is('compliance/reporting*')">Reporting</x-sidebar-link>
            <x-sidebar-link href="/compliance/rules" :active="request()->is('compliance/rules*')">AML Rules</x-sidebar-link>
            <x-sidebar-link href="/compliance/ctos" :active="request()->is('compliance/ctos*')">CTOS</x-sidebar-link>
            <x-sidebar-link href="/compliance/findings" :active="request()->is('compliance/findings*')">Findings</x-sidebar-link>
            <x-sidebar-link href="/compliance/edd-templates" :active="request()->is('compliance/edd-templates*')">EDD Templates</x-sidebar-link>
            <x-sidebar-link href="/compliance/workspace" :active="request()->is('compliance/workspace*')">Workspace</x-sidebar-link>
            <x-sidebar-link href="/compliance/unified" :active="request()->is('compliance/unified*')">Unified Alerts</x-sidebar-link>
        </x-sidebar-dropdown>
    </div>
    @endif

    {{-- Accounting Section (Manager+) --}}
    @if(auth()->check() && auth()->user()->role->isManager())
    <div class="nav-section">
        <x-sidebar-dropdown title="Accounting" icon="book">
            <x-sidebar-link href="/accounting" :active="request()->is('accounting') && !request()->is('accounting/*')">Overview</x-sidebar-link>
            <x-sidebar-link href="/accounting/journal" :active="request()->is('accounting/journal*')">Journal</x-sidebar-link>
            <x-sidebar-link href="/accounting/ledger" :active="request()->is('accounting/ledger*')">Ledger</x-sidebar-link>
            <x-sidebar-link href="/accounting/trial-balance" :active="request()->is('accounting/trial-balance*')">Trial Balance</x-sidebar-link>
            <x-sidebar-link href="/accounting/profit-loss" :active="request()->is('accounting/profit-loss*')">Profit & Loss</x-sidebar-link>
            <x-sidebar-link href="/accounting/balance-sheet" :active="request()->is('accounting/balance-sheet*')">Balance Sheet</x-sidebar-link>
            <x-sidebar-link href="/accounting/reconciliation" :active="request()->is('accounting/reconciliation*')">Reconciliation</x-sidebar-link>
            <x-sidebar-link href="/accounting/budget" :active="request()->is('accounting/budget*')">Budget</x-sidebar-link>
            <x-sidebar-link href="/accounting/revaluation" :active="request()->is('accounting/revaluation*')">Revaluation</x-sidebar-link>
            <x-sidebar-link href="/accounting/month-end" :active="request()->is('accounting/month-end*')">Month End</x-sidebar-link>
            <x-sidebar-link href="/accounting/periods" :active="request()->is('accounting/periods*')">Periods</x-sidebar-link>
            <x-sidebar-link href="/accounting/fiscal-years" :active="request()->is('accounting/fiscal-years*')">Fiscal Years</x-sidebar-link>
        </x-sidebar-dropdown>
    </div>
    @endif

    {{-- Reports Section (Manager+) --}}
    @if(auth()->check() && auth()->user()->role->isManager())
    <div class="nav-section">
        <x-sidebar-dropdown title="Reports" icon="chart">
            <x-sidebar-link href="/reports" :active="request()->is('reports') && !request()->is('reports/*')">Overview</x-sidebar-link>
            <x-sidebar-link href="/reports/msb2" :active="request()->is('reports/msb2*')">MSB2</x-sidebar-link>
            <x-sidebar-link href="/reports/lctr" :active="request()->is('reports/lctr*')">LCTR</x-sidebar-link>
            <x-sidebar-link href="/reports/lmca" :active="request()->is('reports/lmca*')">LMCA</x-sidebar-link>
            <x-sidebar-link href="/reports/quarterly-lvr" :active="request()->is('reports/quarterly-lvr*')">Quarterly LVR</x-sidebar-link>
            <x-sidebar-link href="/reports/position-limit" :active="request()->is('reports/position-limit*')">Position Limits</x-sidebar-link>
            <x-sidebar-link href="/reports/monthly-trends" :active="request()->is('reports/monthly-trends*')">Monthly Trends</x-sidebar-link>
            <x-sidebar-link href="/reports/profitability" :active="request()->is('reports/profitability*')">Profitability</x-sidebar-link>
            <x-sidebar-link href="/reports/customer-analysis" :active="request()->is('reports/customer-analysis*')">Customer Analysis</x-sidebar-link>
            <x-sidebar-link href="/reports/compliance-summary" :active="request()->is('reports/compliance-summary*')">Compliance Summary</x-sidebar-link>
            <x-sidebar-link href="/reports/history" :active="request()->is('reports/history*')">Report History</x-sidebar-link>
        </x-sidebar-dropdown>
    </div>
    @endif

    {{-- System Section (Admin only) --}}
    @if(auth()->check() && auth()->user()->role->isAdmin())
    <div class="nav-section">
        <x-sidebar-dropdown title="System" icon="cog">
            <x-sidebar-link href="/users" :active="request()->is('users*')">Users</x-sidebar-link>
            <x-sidebar-link href="/branches" :active="request()->is('branches*')">Branches</x-sidebar-link>
            <x-sidebar-link href="/audit" :active="request()->is('audit*')">Audit</x-sidebar-link>
        </x-sidebar-dropdown>
    </div>
    @endif

</nav>
```

### Step 2: Create sidebar components

Create `resources/views/components/sidebar-dropdown.blade.php`:

```blade
@props(['title', 'icon' => 'home'])

<div x-data="{ open: false }">
    <button
        @click="open = !open"
        class="nav-section-title w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-[--sidebar-text-muted] uppercase tracking-wider hover:text-[--sidebar-text] transition-colors"
    >
        <span class="flex items-center gap-2">
            <x-icon name="{{ $icon }}" class="w-4 h-4" />
            {{ $title }}
        </span>
        <svg :class="{ 'rotate-180': open }" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    <div x-show="open" x-collapse class="space-y-1 px-2 py-1">
        {{ $slot }}
    </div>
</div>
```

Create `resources/views/components/sidebar-link.blade.php`:

```blade
@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    class="nav-item {{ $active ? 'active' : '' }}"
>
    {{ $slot }}
</a>
```

Create `resources/views/components/icon.blade.php`:

```blade
@props(['name'])

@switch($name)
    @case('home')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
        </svg>
        @break
    @case('cash')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 657a2 2 0 100-4 2 2 0 000 4zm0 0v6a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2v6z"></path>
        </svg>
        @break
    @case('register')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
        </svg>
        @break
    @case('boxes')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
        </svg>
        @break
    @case('shield')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
        </svg>
        @break
    @case('book')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
        </svg>
        @break
    @case('chart')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
        </svg>
        @break
    @case('cog')
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.544-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.544-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.544.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.544.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        @break
    @default
        <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
@endswitch
```

### Step 3: Verify in browser

1. Run `npm run dev` if not already running
2. Navigate to http://local.host and login
3. Verify sidebar shows all sections based on role
4. Click section headers to verify dropdowns expand/collapse
5. Click links to verify navigation works

---

## Summary

| Task | Action |
|------|--------|
| 1 | Replace sidebar in base.blade.php with dropdown navigation |
| 2 | Create sidebar dropdown and link Blade components |
| 3 | Create icon Blade component |
| 4 | Verify in browser |

---

**Plan saved to:** `docs/superpowers/plans/2026-05-01-sidebar-navigation-plan.md`

**Two execution options:**

**1. Subagent-Driven (recommended)** - Dispatch fresh subagent per task, review between tasks

**2. Inline Execution** - Execute tasks in this session using executing-plans skill

Which approach?