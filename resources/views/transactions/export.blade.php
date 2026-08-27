<x-app-layout title="Export Transactions">
    <div class="space-y-6">
        <x-page-header title="Export Transactions" description="Export transaction data to CSV" />

        <x-card>
            <form action="{{ route('transactions.export.export') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input type="date" name="date_from" label="Date From" />
                    <x-input type="date" name="date_to" label="Date To" />
                    <x-select name="branch_id" label="Branch" :options="['' => 'All Branches'] + $branches->pluck('name', 'id')->toArray()" />
                    <x-select name="type" label="Transaction Type" :options="['' => 'All Types'] + $types->mapWithKeys(fn ($t) => [$t->value => $t->label()])->toArray()" />
                    <div class="md:col-span-2">
                        <x-select name="status" label="Status" :options="['' => 'All Statuses', 'completed' => 'Completed', 'pending' => 'Pending', 'cancelled' => 'Cancelled']" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-button type="submit" variant="primary">Export CSV</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
