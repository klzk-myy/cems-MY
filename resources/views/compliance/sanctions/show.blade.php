<x-app-layout title="Sanctions List Details">
    <div class="space-y-6">
        <x-page-header
            title="Sanctions List Details"
            description="{{ $list->name }}"
            :actions="true"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.sanctions.index') }}">
                    Back to Lists
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="List Information" :actions="true">
            <x-slot:actions>
                <x-badge variant="success">
                    {{ $list->update_status?->label() ?? ucfirst((string) $list->update_status) }}
                </x-badge>
            </x-slot:actions>

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
                            <a href="{{ $list->source_url }}" target="_blank" rel="noopener" class="text-info hover:text-info-hover">
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
                    <p class="text-sm text-ink">{{ $list->update_status ?: 'N/A' }}</p>
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
        </x-card>

        <x-card title="Actions">
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('compliance.sanctions.import', $list) }}">
                    @csrf
                    <x-button variant="primary" type="submit">Trigger Import</x-button>
                </form>
                <x-button variant="secondary" href="{{ route('compliance.sanctions.entries.index', ['list_id' => $list->id]) }}">
                    View Entries
                </x-button>
            </div>
        </x-card>
    </div>
</x-app-layout>
