<x-app-layout title="Findings">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-ink">Finding Details</h1>
                    <p class="mt-1 text-sm text-ink-muted">FIND-{{ $finding->id }}</p>
                </div>
                <a href="{{ route('compliance.findings.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Finding Overview -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Title</label>
                    <p class="text-sm text-ink">{{ $finding->finding_type?->label() ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Severity</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">{{ $finding->severity?->label() ?? '—' }}</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Category</label>
                    {{-- TODO: Wire to a category field/model when one exists. --}}
                    <p class="text-sm text-ink">{{ $finding->category?->label() ?? $finding->category ?? 'Compliance' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Status</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">{{ $finding->status?->label() ?? '—' }}</span>
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
        </div>

        <!-- Description -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Description</h3>
            <p class="text-sm text-gray-600">
                @if (is_array($finding->details))
                    {{ $finding->details['description'] ?? 'No description provided.' }}
                @else
                    {{ $finding->details ?: 'No description provided.' }}
                @endif
            </p>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-ink mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button type="button" disabled class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white opacity-50 cursor-not-allowed">
                    Update Status
                </button>
                <button type="button" disabled class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] opacity-50 cursor-not-allowed">
                    Add Note
                </button>
                <button type="button" disabled class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] opacity-50 cursor-not-allowed">
                    Assign
                </button>
                <button type="button" disabled class="px-4 py-2 text-sm font-medium rounded-lg bg-green-50 border border-green-200 text-green-700 opacity-50 cursor-not-allowed">
                    Mark Resolved
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
