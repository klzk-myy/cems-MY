<x-app-layout title="Customer Details">
    <x-page-header title="Customer Details" description="View customer information">
        <x-slot:actions>
            <a href="{{ route('customers.edit', $customer) }}"><x-button variant="primary">Edit</x-button></a>
            <a href="{{ route('customers.index') }}"><x-button variant="secondary">Back</x-button></a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Personal Information">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-ink-muted">Name</dt>
                        <dd class="font-medium text-ink">{{ $customer->name ?? 'John Doe' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Email</dt>
                        <dd class="font-medium text-ink">{{ $customer->email ?? 'john@example.com' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Phone</dt>
                        <dd class="font-medium text-ink">{{ $customer->phone ?? '+60 12-345 6789' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">Status</dt>
                        <dd><x-badge variant="success">Active</x-badge></dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Add Note">
                <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="space-y-3">
                    @csrf
                    <x-textarea name="note" placeholder="Enter your note..." :required="true" />
                    <div class="flex justify-end">
                        <x-button type="submit" variant="primary">Add Note</x-button>
                    </div>
                </form>
            </x-card>
        </div>
        <div>
            <x-card title="Account Summary">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Total Transactions</dt>
                        <dd class="font-medium text-ink">15</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Total Volume</dt>
                        <dd class="font-medium text-ink">RM 125,000</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Member Since</dt>
                        <dd class="font-medium text-ink">Jan 2024</dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
