<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[--color-ink]">Cash Flow</h1>
        <p class="text-sm text-[--color-ink-muted]">Cash flow statement by activity type</p>
    </div>

    {{-- Date Range Filter --}}
    <div class="card mb-6">
        <div class="card-body flex items-center gap-4">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium">From:</label>
                <input type="date" wire:model.live="fromDate" class="input w-auto" />
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium">To:</label>
                <input type="date" wire:model.live="toDate" class="input w-auto" />
            </div>
        </div>
    </div>

    @if($hasData && !empty($cashFlow))
    {{-- Cash Flow Statement --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Cash Flow Statement</h3>
            <span class="text-sm text-[--color-ink-muted]">{{ $fromDate }} - {{ $toDate }}</span>
        </div>
        <div class="card-body">
            {{-- Opening Balance --}}
            <div class="mb-6 p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex justify-between">
                    <span class="font-medium">Opening Cash Balance</span>
                    <span class="font-mono">{{ number_format((float) ($cashFlow['opening_balance'] ?? 0), 2) }} MYR</span>
                </div>
            </div>

            {{-- Operating Activities --}}
            <h4 class="font-semibold mb-4 text-[--color-primary]">Operating Activities</h4>
            <div class="space-y-2 mb-6">
                @forelse($cashFlow['operating'] ?? [] as $key => $value)
                @if(!is_array($value))
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span class="text-sm text-[--color-ink-muted]">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                    <span class="font-mono {{ (float) $value < 0 ? 'text-[--color-danger]' : '' }}">{{ number_format((float) $value, 2) }}</span>
                </div>
                @endif
                @empty
                <p class="text-[--color-ink-muted]">No operating cash flow data</p>
                @endforelse
            </div>

            {{-- Investing Activities --}}
            <h4 class="font-semibold mb-4 text-[--color-accent]">Investing Activities</h4>
            <div class="space-y-2 mb-6">
                @forelse($cashFlow['investing'] ?? [] as $key => $value)
                @if(!is_array($value))
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span class="text-sm text-[--color-ink-muted]">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                    <span class="font-mono {{ (float) $value < 0 ? 'text-[--color-danger]' : '' }}">{{ number_format((float) $value, 2) }}</span>
                </div>
                @endif
                @empty
                <p class="text-[--color-ink-muted]">No investing cash flow data</p>
                @endforelse
            </div>

            {{-- Financing Activities --}}
            <h4 class="font-semibold mb-4 text-[--color-info]">Financing Activities</h4>
            <div class="space-y-2 mb-6">
                @forelse($cashFlow['financing'] ?? [] as $key => $value)
                @if(!is_array($value))
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span class="text-sm text-[--color-ink-muted]">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                    <span class="font-mono {{ (float) $value < 0 ? 'text-[--color-danger]' : '' }}">{{ number_format((float) $value, 2) }}</span>
                </div>
                @endif
                @empty
                <p class="text-[--color-ink-muted]">No financing cash flow data</p>
                @endforelse
            </div>

            {{-- Net Cash Change --}}
            <div class="flex justify-between text-lg font-bold mt-6 pt-4 border-t-2 border-[--color-ink]">
                <span>Net Cash Change</span>
                <span class="font-mono {{ (float) ($cashFlow['net_change'] ?? 0) < 0 ? 'text-[--color-danger]' : 'text-[--color-success]' }}">
                    {{ number_format((float) ($cashFlow['net_change'] ?? 0), 2) }} MYR
                </span>
            </div>

            {{-- Closing Balance --}}
            <div class="mt-4 p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex justify-between font-semibold">
                    <span>Closing Cash Balance</span>
                    <span class="font-mono">{{ number_format((float) ($cashFlow['closing_balance'] ?? 0), 2) }} MYR</span>
                </div>
            </div>
        </div>
    </div>
    @else
    {{-- Empty State --}}
    <div class="card">
        <div class="empty-state py-12">
            <div class="empty-state-icon">
                <svg class="w-12 h-12 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="empty-state-title">No cash flow data</p>
            <p class="empty-state-description">Select a date range to view the cash flow statement</p>
        </div>
    </div>
    @endif
</div>