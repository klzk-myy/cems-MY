<x-app-layout title="Findings">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header title="Finding Details" :actions="true">
            FIND-{{ $finding->id }}
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.findings.index') }}">Back to List</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Overview">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Title</label>
                    <p class="text-sm text-ink">{{ $finding->finding_type?->label() ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Severity</label>
                    <x-badge variant="danger">{{ $finding->severity?->label() ?? '—' }}</x-badge>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Category</label>
                    {{-- TODO: Wire to a category field/model when one exists. --}}
                    <p class="text-sm text-ink">{{ $finding->category?->label() ?? $finding->category ?? 'Compliance' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Status</label>
                    <x-badge variant="warning">{{ $finding->status?->label() ?? '—' }}</x-badge>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Generated At</label>
                    <p class="text-sm text-ink">{{ $finding->generated_at?->format('Y-m-d H:i') ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Subject</label>
                    <p class="text-sm text-ink">{{ $finding->subject?->name ?? '—' }}</p>
                </div>
            </div>
        </x-card>

        <x-card title="Description">
            <p class="text-sm text-ink-muted">
                @if (is_array($finding->details))
                    {{ $finding->details['description'] ?? 'No description provided.' }}
                @else
                    {{ $finding->details ?: 'No description provided.' }}
                @endif
            </p>
        </x-card>

        <x-card title="Actions">
            <div class="flex flex-wrap gap-3">
                <x-button variant="primary" disabled>Update Status</x-button>
                <x-button variant="secondary" disabled>Add Note</x-button>
                <x-button variant="secondary" disabled>Assign</x-button>
                <x-button variant="secondary" disabled>Mark Resolved</x-button>
            </div>
        </x-card>
    </div>
</x-app-layout>
