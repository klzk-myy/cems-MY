@props([
    'collapsible' => true,
    'collapsed' => false,
])

@php
$navItems = [
    'dashboard' => ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    'transactions' => ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z'],
    'customers' => ['route' => 'customers.index', 'label' => 'Customers', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    'rates' => ['route' => 'rates.index', 'label' => 'Rates', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    'counters' => ['route' => 'counters.index', 'label' => 'Counters', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
    'stock-cash' => ['route' => 'stock-cash.index', 'label' => 'Stock Cash', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
    'stock-transfers' => ['route' => 'stock-transfers.index', 'label' => 'Stock Transfers', 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
    'compliance' => ['route' => 'compliance', 'label' => 'Compliance', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
    'accounting' => ['route' => 'accounting.index', 'label' => 'Accounting', 'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
    'reports' => ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    'users' => ['route' => 'users.index', 'label' => 'Users', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    'branches' => ['route' => 'branches.index', 'label' => 'Branches', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
    'system.currencies' => ['route' => 'system.currencies.index', 'label' => 'Currencies', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
];

$currentRoute = request()->route()?->getName() ?? '';

// Hide nav links the current user is not authorized to reach (route-driven,
// so it always matches the real middleware protection on each route).
$userRole = auth()->user()?->role?->value;
$filteredNav = [];
foreach ($navItems as $key => $item) {
    $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($item['route'] ?? null);
    $allowed = true;
    if ($route !== null) {
        foreach ($route->middleware() as $mw) {
            if (preg_match('/^role:(.+)$/', $mw, $m)) {
                $roles = explode(',', $m[1]);
                if ($userRole === null || ! in_array($userRole, $roles, true)) {
                    $allowed = false;
                    break;
                }
            }
        }
    }
    if ($allowed) {
        $filteredNav[$key] = $item;
    }
}
@endphp

<aside
    {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'flex h-full w-64 flex-col border-r border-sidebar-border bg-surface-inverted transition-all']) }}
    :class="{ 'w-20': sidebarCollapsed }"
>
    <div class="flex h-16 items-center justify-between border-b border-sidebar-border px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2" x-show="!sidebarCollapsed">
            <span class="text-lg font-bold text-sidebar-text">CEMS</span>
        </a>
        @if($collapsible)
            <button
                type="button"
                @click="sidebarCollapsed = !sidebarCollapsed"
                :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                class="rounded-md p-1.5 text-sidebar-text-muted hover:bg-sidebar-hover hover:text-sidebar-text"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        @endif
    </div>

    <nav class="flex-1 overflow-y-auto p-3">
        <ul class="space-y-1">
            @foreach($filteredNav as $key => $item)
                @php
                    // Check for exact route match or prefix match with delimiter to avoid partial matches
                    $isActive = $currentRoute === $item['route']
                        || str_starts_with($currentRoute, $key . '.')
                        || str_starts_with($currentRoute, $key . '-');
                @endphp
                <li>
                    <a
                        href="{{ route($item['route']) }}"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors {{ $isActive ? 'bg-sidebar-hover text-sidebar-text' : 'text-sidebar-text hover:bg-sidebar-hover' }}"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}" />
                        </svg>
                        <span :class="{ 'sr-only': sidebarCollapsed }">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="border-t border-sidebar-border p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span :class="{ 'sr-only': sidebarCollapsed }">Logout</span>
            </button>
        </form>
    </div>
</aside>
