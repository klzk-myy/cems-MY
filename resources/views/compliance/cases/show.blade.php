<x-app-layout title="Case Details">
    <x-page-header title="Case Details" description="View case information">
        <x-slot:actions>
            <a href="{{ route('compliance.cases.index') }}"><x-button variant="secondary">Back</x-button></a>
        </x-slot:actions>
    </x-page-header>

    <x-card title="Case Information">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-ink-muted">Case ID</dt>
                <dd class="font-medium text-ink">{{ $case->id }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Type</dt>
                <dd class="font-medium text-ink">{{ $case->case_type?->value ?? $case->type }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Subject</dt>
                <dd class="font-medium text-ink">{{ $case->subject }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Status</dt>
                <dd><x-badge variant="warning">{{ $case->status }}</x-badge></dd>
            </div>
            <div>
                <dt class="text-ink-muted">Assignee</dt>
                <dd class="font-medium text-ink">{{ $case->assignee?->name ?? 'Unassigned' }}</dd>
            </div>
            <div>
                <dt class="text-ink-muted">Created</dt>
                <dd class="font-medium text-ink">{{ $case->created_at?->format('d M Y H:i') }}</dd>
            </div>
        </dl>
    </x-card>

    <x-card title="Description" class="mt-6">
        <p class="text-sm text-ink">{{ $case->description ?? 'No description available.' }}</p>
    </x-card>
</x-app-layout>
