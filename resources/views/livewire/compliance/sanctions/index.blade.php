<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Sanctions Screening</h1>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form wire:submit="search" class="flex gap-4">
                <input type="text" wire:model="searchQuery" placeholder="Search by name, entity, or reference..." class="flex-1 rounded border border-[var(--color-border)] px-3 py-2" />
                <select wire:model="searchType" class="rounded border border-[var(--color-border)] px-3 py-2">
                    <option value="name">Name</option>
                    <option value="entity">Entity</option>
                    <option value="reference">Reference</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Search</button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Name/Entity</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Type</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">List</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Match Score</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($results as $result)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $result->name }}</td>
                        <td class="px-4 py-3">{{ $result->entity_type }}</td>
                        <td class="px-4 py-3">{{ $result->list_name }}</td>
                        <td class="px-4 py-3">
                            @if($result->match_score >= 90)
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">{{ $result->match_score }}%</span>
                            @elseif($result->match_score >= 70)
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">{{ $result->match_score }}%</span>
                            @else
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">{{ $result->match_score }}%</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($result->status === 'cleared')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Cleared</span>
                            @elseif($result->status === 'potential_match')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Potential</span>
                            @else
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">Hit</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No results found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>