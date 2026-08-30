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
                    <x-detail-row label="Name">{{ $customer->name ?? 'John Doe' }}</x-detail-row>
                    <x-detail-row label="Email">{{ $customer->email ?? 'john@example.com' }}</x-detail-row>
                    <x-detail-row label="Phone">{{ $customer->phone ?? '+60 12-345 6789' }}</x-detail-row>
                    <x-detail-row label="Status" value-class=""><x-badge variant="success">Active</x-badge></x-detail-row>
                </dl>
            </x-card>

            <x-card title="Notes">
                @forelse($customer->notes ?? [] as $note)
                    <div class="border-b border-border pb-3 last:border-0 last:pb-0">
                        <p class="text-sm text-ink">{{ $note->note }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ $note->createdBy?->name ?? 'Unknown' }} &middot; {{ $note->created_at?->format('d M Y H:i') }}</p>
                    </div>
                @empty
                    <p class="text-sm text-ink-muted">No notes yet.</p>
                @endforelse
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
                    <x-detail-row label="Total Transactions" row-class="flex justify-between">{{ $customer->transactions->count() ?? 0 }}</x-detail-row>
                    <x-detail-row label="Member Since" row-class="flex justify-between">{{ $customer->created_at?->format('M Y') ?? 'Jan 2024' }}</x-detail-row>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
