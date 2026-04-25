<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[--color-ink]">Budget vs Actual</h1>
        <p class="text-sm text-[--color-ink-muted]">Monitor budget performance by account</p>
    </div>

    {{-- Period Selector --}}
    <div class="card mb-6">
        <div class="card-body flex items-center gap-4">
            <label class="text-sm text-[--color-ink-muted]">Period:</label>
            <input
                type="month"
                wire:model.live="periodCode"
                class="form-input w-40">
            <span class="text-sm text-[--color-ink-muted]">
                Showing budget data for {{ $periodCode }}
            </span>
        </div>
    </div>

    {{-- Budget vs Actual Table --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Budget vs Actual</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="text-right">Budget</th>
                        <th class="text-right">Actual</th>
                        <th class="text-right">Variance</th>
                        <th class="text-right">Variance %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report as $item)
                    <tr>
                        <td>
                            <div>
                                <span class="font-mono text-sm">{{ $item['account_code'] }}</span>
                                <span class="ml-2">{{ $item['account_name'] ?? '' }}</span>
                            </div>
                        </td>
                        <td class="font-mono text-right">{{ number_format($item['budget'] ?? 0, 2) }}</td>
                        <td class="font-mono text-right">{{ number_format($item['actual'] ?? 0, 2) }}</td>
                        <td class="font-mono text-right {{ ($item['variance'] ?? 0) >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                            {{ number_format($item['variance'] ?? 0, 2) }}
                        </td>
                        <td class="font-mono text-right {{ ($item['variance_percentage'] ?? 0) >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                            {{ number_format($item['variance_percentage'] ?? 0, 1) }}%
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No budget data for this period</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Unbudgeted Accounts --}}
    @if(!empty($unbudgetedAccounts))
    <div class="card mt-6">
        <div class="card-header">
            <h3 class="card-title">Accounts Without Budget</h3>
        </div>
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted] mb-4">The following accounts have no budget set for this period:</p>
            <div class="flex flex-wrap gap-2">
                @foreach($unbudgetedAccounts as $account)
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-[--color-surface] text-sm">
                    <span class="font-mono">{{ $account['account_code'] ?? $account }}</span>
                </span>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>