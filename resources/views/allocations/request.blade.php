<x-app-layout title="Request Stock">
    <div class="space-y-6">
        <x-page-header title="Request Stock" description="Request a currency allocation from the branch pool">
            <x-slot:actions>
                <x-button href="{{ route('my-allocations.index') }}" variant="secondary">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card>
            <form method="POST" action="{{ route('my-allocations.request.store') }}">
                @csrf
                <x-select
                    name="currency_code"
                    label="Currency"
                    :options="$currencies->pluck('code', 'code')->toArray()"
                    placeholder="Select a currency"
                    required
                />
                <x-input
                    name="requested_amount"
                    label="Requested Amount"
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    required
                />
                <x-select
                    name="counter_id"
                    label="Counter (optional)"
                    :options="$counters->pluck('name', 'id')->toArray()"
                    placeholder="Any counter"
                />
                <div class="flex gap-3 mt-6">
                    <x-button type="submit">Submit Request</x-button>
                    <x-button href="{{ route('my-allocations.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
