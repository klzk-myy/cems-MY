@props(['method' => null, 'action' => null])

@if($method)
<form method="{{ $method }}" action="{{ $action ?? url()->current() }}" {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'flex flex-wrap items-center gap-3 rounded-xl border border-border bg-surface p-4']) }}>
    {{ $slot }}
</form>
@else
<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'flex flex-wrap items-center gap-3 rounded-xl border border-border bg-surface p-4']) }}>
    {{ $slot }}
</div>
@endif
