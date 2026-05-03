<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">AML Rules Configuration</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Rule Name</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Type</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Threshold</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $rule->name }}</td>
                        <td class="px-4 py-3">{{ $rule->type }}</td>
                        <td class="px-4 py-3">{{ $rule->threshold }}</td>
                        <td class="px-4 py-3">
                            @if($rule->is_active)
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Active</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <button wire:click="toggle({{ $rule->id }})" class="text-blue-600 hover:underline">
                                {{ $rule->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No rules configured</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>