<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-border bg-surface']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-border bg-canvas-subtle text-xs uppercase tracking-wider text-ink-muted">
                {{ $thead }}
            </thead>
            <tbody class="divide-y divide-border">
                {{ $tbody }}
            </tbody>
        </table>
    </div>
</div>
