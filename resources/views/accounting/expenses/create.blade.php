<x-app-layout title="New Expense">
    <div class="space-y-6">
        <x-page-header title="Post Petty Cash Expense" description="Debit an expense account against the branch petty cash float" />

        <x-card>
            <form method="POST" action="{{ route('accounting.expenses.store') }}" class="space-y-4">
                @csrf

                @if($branches->count() > 1)
                    <x-select name="branch_id" label="Branch" :options="$branches->pluck('name', 'id')->all()" required />
                @else
                    <input type="hidden" name="branch_id" value="{{ $branches->first()?->id }}">
                    <div class="text-sm"><span class="text-ink-muted">Branch:</span> {{ $branches->first()?->name ?? '—' }}</div>
                @endif

                <x-select name="account_code" label="Expense Account" :options="$expenseAccounts" required placeholder="Select expense account" />

                <x-input name="category" label="Category" placeholder="e.g. Office supplies, Utilities" required maxlength="100" />

                <x-input name="description" label="Description" placeholder="Expense description" required maxlength="500" />

                <x-input name="amount_myr" label="Amount (MYR)" type="number" step="0.01" min="0.01" required />

                <x-input name="expense_date" label="Expense Date" type="date" :value="now()->toDateString()" />

                <div class="flex gap-3 pt-2">
                    <x-button type="submit" variant="primary">Post Expense</x-button>
                    <x-button href="{{ route('accounting.expenses.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
