@php
$biLinks = [
    ['route' => 'bi.dashboard', 'label' => 'Dashboard'],
    ['route' => 'bi.branches', 'label' => 'Branches'],
    ['route' => 'bi.currencies', 'label' => 'Currencies'],
    ['route' => 'bi.rates', 'label' => 'Rates'],
    ['route' => 'bi.operations', 'label' => 'Operations'],
    ['route' => 'bi.customers', 'label' => 'Customers'],
    ['route' => 'bi.forecast', 'label' => 'Forecast'],
    ['route' => 'bi.alerts', 'label' => 'Alerts'],
];
@endphp

<nav class="flex flex-wrap gap-2" aria-label="BI sections">
    @foreach($biLinks as $link)
        <a
            href="{{ route($link['route'], request()->only('start_date', 'end_date', 'branch_id')) }}"
            class="px-3 py-1.5 text-sm rounded-lg border transition-colors
                {{ request()->routeIs($link['route'])
                    ? 'bg-primary text-on-primary border-primary font-medium'
                    : 'bg-surface border-border text-ink-muted hover:text-ink hover:border-ink-muted' }}"
            @if(request()->routeIs($link['route'])) aria-current="page" @endif
        >{{ $link['label'] }}</a>
    @endforeach
</nav>
