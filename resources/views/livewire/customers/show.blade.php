<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('customers.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Customers</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">{{ $customer->name }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Email</label>
                    <p class="mt-1">{{ $customer->email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Phone</label>
                    <p class="mt-1">{{ $customer->phone }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Status</label>
                    <p class="mt-1">
                        @if($customer->is_active)
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Active</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded">Inactive</span>
                        @endif
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Customer Since</label>
                    <p class="mt-1">{{ $customer->created_at->format('Y-m-d') }}</p>
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('customers.edit', $customer->id) }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Edit</a>
            </div>
        </div>
    </div>
</div>