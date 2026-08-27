<x-app-layout title="Pool: {{ $branchPool->branch?->name }} - {{ $branchPool->currency_code }}">
    <div class="space-y-6">
        <x-page-header title="{{ $branchPool->branch?->name }} - {{ $branchPool->currency_code }}" :description="'Branch Pool'">
            <x-slot:actions>
                <x-button href="{{ route('branch-pools.index') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-card title="Available Balance">
                <p class="text-2xl font-bold">{{ number_format((float) $branchPool->available_balance, 4) }}</p>
            </x-card>
            <x-card title="Allocated Balance">
                <p class="text-2xl font-bold">{{ number_format((float) $branchPool->allocated_balance, 4) }}</p>
            </x-card>
            <x-card title="Total Balance">
                <p class="text-2xl font-bold">{{ number_format((float) ($branchPool->available_balance + $branchPool->allocated_balance), 4) }}</p>
            </xcard>
        </div>

        @if(auth()->user()?->role->value !== 'teller')
            <x-card title="Fund Pool">
                <form action="{{ route('branch-pools.fund', $branchPool->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="fund-amount" class="block text-sm font-medium mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="fund-amount" required class="w-full rounded-md border-border bg-surface text-ink text-sm">
                    </div>
                    <x-button type="submit" variant="success">Fund</x-button>
                </form>
            </x-card>

            <x-card title="Debit Pool">
                <form action="{{ route('branch-pools.debit', $branchPool->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="debit-amount" class="block text-sm font-medium mb-1">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="debit-amount" required class="w-full rounded-md border-border bg-surface text-ink text-sm">
                    </div>
                    <x-button type="submit" variant="danger">Debit</x-button>
                </form>
            </x-card>
        @endif
    </div>
</x-app-layout>
