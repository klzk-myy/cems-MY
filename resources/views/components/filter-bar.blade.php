<div {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'flex flex-wrap items-center gap-3 rounded-xl border border-border bg-surface p-4']) }}>
    {{ $slot }}
</div>
