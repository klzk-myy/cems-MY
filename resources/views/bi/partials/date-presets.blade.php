{{-- Quick period presets — today / 7d / MTD / QTD. Preserves the other
     active filters (branch_id, currency) while replacing the dates. --}}
@php
    $presets = [
        'Today' => [today(), today()],
        '7D' => [today()->subDays(6), today()],
        'MTD' => [today()->startOfMonth(), today()],
        'QTD' => [today()->startOfQuarter(), today()],
    ];
    $extra = request()->except(['start_date', 'end_date', 'page']);
@endphp
<div class="flex flex-wrap items-center gap-2 -mt-2">
    <span class="text-xs text-ink-muted">Presets:</span>
    @foreach($presets as $label => [$presetFrom, $presetTo])
        @php($active = $from === $presetFrom->toDateString() && $to === $presetTo->toDateString())
        <a href="{{ url()->current() }}?{{ http_build_query(array_merge($extra, ['start_date' => $presetFrom->toDateString(), 'end_date' => $presetTo->toDateString()])) }}"
           class="rounded-md px-2.5 py-1 text-xs font-medium {{ $active ? 'bg-primary/15 text-primary' : 'bg-surface-alt text-ink-muted hover:text-ink dark:text-ink-dark-muted dark:hover:text-ink-dark' }}">{{ $label }}</a>
    @endforeach
</div>
