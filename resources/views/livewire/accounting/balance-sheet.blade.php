<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[--color-ink]">Balance Sheet</h1>
        <p class="text-sm text-[--color-ink-muted]">Assets, liabilities, and equity</p>
    </div>

    {{-- Date Filter --}}
    <div class="card mb-6">
        <div class="card-body flex items-center gap-4">
            <label class="text-sm font-medium">As of Date:</label>
            <input type="date" wire:model.live="asOfDate" class="input w-auto" />
        </div>
    </div>

    {{-- Balance Sheet --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Balance Sheet</h3>
            <span class="text-sm text-[--color-ink-muted]">As of {{ $asOfDate }}</span>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Assets --}}
                <div>
                    <h4 class="font-semibold mb-4 text-[--color-primary]">Assets</h4>
                    @forelse($balanceSheet['assets'] ?? [] as $item)
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>{{ $item['account_name'] ?? $item['name'] ?? 'N/A' }}</span>
                        <span class="font-mono">{{ number_format((float) ($item['balance'] ?? 0), 2) }}</span>
                    </div>
                    @empty
                    <p class="text-[--color-ink-muted] py-2">No assets</p>
                    @endforelse
                    <div class="flex justify-between py-3 font-semibold border-t-2 border-[--color-ink] mt-2">
                        <span>Total Assets</span>
                        <span class="font-mono text-[--color-primary]">{{ number_format((float) ($balanceSheet['total_assets'] ?? 0), 2) }}</span>
                    </div>
                </div>

                {{-- Liabilities & Equity --}}
                <div>
                    <h4 class="font-semibold mb-4 text-[--color-danger]">Liabilities</h4>
                    @forelse($balanceSheet['liabilities'] ?? [] as $item)
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>{{ $item['account_name'] ?? $item['name'] ?? 'N/A' }}</span>
                        <span class="font-mono">{{ number_format((float) ($item['balance'] ?? 0), 2) }}</span>
                    </div>
                    @empty
                    <p class="text-[--color-ink-muted] py-2">No liabilities</p>
                    @endforelse

                    <h4 class="font-semibold mb-4 mt-6 text-[--color-info]">Equity</h4>
                    @forelse($balanceSheet['equity'] ?? [] as $item)
                    <div class="flex justify-between py-2 border-b border-[--color-border]">
                        <span>{{ $item['account_name'] ?? $item['name'] ?? 'N/A' }}</span>
                        <span class="font-mono">{{ number_format((float) ($item['balance'] ?? 0), 2) }}</span>
                    </div>
                    @empty
                    <p class="text-[--color-ink-muted] py-2">No equity accounts</p>
                    @endforelse

                    <div class="flex justify-between py-3 font-semibold border-t-2 border-[--color-ink] mt-4">
                        <span>Total Liabilities & Equity</span>
                        <span class="font-mono text-[--color-info]">{{ number_format((float) ($balanceSheet['liabilities_plus_equity'] ?? 0), 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Balance Verification --}}
            @if(isset($balanceSheet['is_balanced']))
            <div class="mt-8 pt-4 border-t-2 border-[--color-ink]">
                @if($balanceSheet['is_balanced'])
                <div class="alert alert-success">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Balance sheet is balanced (Assets = Liabilities + Equity)</span>
                </div>
                @else
                <div class="alert alert-danger">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span>Balance sheet is NOT balanced - investigate discrepancy</span>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>