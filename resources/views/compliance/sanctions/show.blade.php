<x-app-layout title="Sanctions List Details">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-ink">Sanctions List Details</h1>
                    <p class="mt-1 text-sm text-ink-muted">{{ $list->name }}</p>
                </div>
                <a href="{{ route('compliance.sanctions.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    Back to Lists
                </a>
            </div>
        </div>

        <!-- List Details -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-ink">List Information</h3>
                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                    {{ ucfirst($list->update_status?->value ?? (string) $list->update_status) }}
                </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Name</label>
                    <p class="text-sm text-ink">{{ $list->name }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">List Type</label>
                    <p class="text-sm text-ink">{{ $list->list_type?->value ?? (string) $list->list_type }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Source URL</label>
                    <p class="text-sm text-ink">
                        @if ($list->source_url)
                            <a href="{{ $list->source_url }}" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800">
                                {{ $list->source_url }}
                            </a>
                        @else
                            N/A
                        @endif
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Source Format</label>
                    <p class="text-sm text-ink">{{ $list->source_format ?: 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Update Frequency</label>
                    <p class="text-sm text-ink">{{ $list->update_frequency ?: 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Last Synced At</label>
                    <p class="text-sm text-ink">{{ $list->last_updated_at?->toIso8601String() ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Entries Count</label>
                    <p class="text-sm text-ink">{{ $list->entries_count ?? 0 }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Active</label>
                    <p class="text-sm text-ink">{{ $list->is_active ? 'Yes' : 'No' }}</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('compliance.sanctions.import', $list) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Trigger Import
                    </button>
                </form>
                <a href="{{ route('compliance.sanctions.entries.index', ['list_id' => $list->id]) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    View Entries
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
