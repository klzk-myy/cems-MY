<x-app-layout title="New Allocation">
    <div class="space-y-6">
        <x-page-header title="New Allocation" description="Assign stock from the branch pool to a teller">
            <x-slot:actions>
                <x-button href="{{ route('allocations.index') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <form method="POST" action="{{ route('allocations.store') }}">
                @csrf
                <x-select
                    name="user_id"
                    label="Teller"
                    :options="$tellers->mapWithKeys(fn ($t) => [$t->id => $t->username])->toArray()"
                    placeholder="Select a teller"
                    required
                />
                <x-select
                    name="currency_code"
                    label="Currency"
                    :options="$currencies->mapWithKeys(fn ($c) => [$c->code => $c->code . ($pools->has($c->code) ? ' — pool: ' . number_format((float) $pools->get($c->code)->available_balance, 2) : '')])->toArray()"
                    placeholder="Select a currency"
                    required
                />
                <x-input
                    name="amount"
                    label="Amount"
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    required
                />
                <x-input
                    name="daily_limit_myr"
                    label="Daily Limit (MYR, optional)"
                    type="number"
                    step="0.01"
                    min="0"
                />
                <p class="mt-3 text-xs text-ink-muted">
                    Funds move from the branch pool immediately. The teller confirms receipt via Accept on My Allocations.
                </p>
                <div class="flex gap-3 mt-6">
                    <x-button type="submit">Allocate Stock</x-button>
                    <x-button href="{{ route('allocations.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
