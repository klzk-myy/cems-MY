<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('branches.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Branches</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">{{ $branch->name }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Branch Code</label>
                    <p class="mt-1">{{ $branch->code }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Location</label>
                    <p class="mt-1">{{ $branch->location }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Status</label>
                    <p class="mt-1">
                        @if($branch->is_active)
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Active</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded">Inactive</span>
                        @endif
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Created</label>
                    <p class="mt-1">{{ $branch->created_at->format('Y-m-d') }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-4">
                <a href="{{ route('branches.edit', $branch->id) }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Edit</a>
            </div>
        </div>
    </div>
</div>