<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Branches</h1>
            <a href="{{ route('branches.create') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Add Branch</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Name</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Code</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Location</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branches as $branch)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $branch->name }}</td>
                        <td class="px-4 py-3">{{ $branch->code }}</td>
                        <td class="px-4 py-3">{{ $branch->location }}</td>
                        <td class="px-4 py-3">
                            @if($branch->is_active)
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Active</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('branches.show', $branch->id) }}" class="text-blue-600 hover:underline mr-2">View</a>
                            <a href="{{ route('branches.edit', $branch->id) }}" class="text-blue-600 hover:underline mr-2">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No branches found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $branches->links() }}
        </div>
    </div>
</div>