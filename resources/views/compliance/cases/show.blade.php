<x-app-layout title="Case Details">
    <x-page-header title="Case Details" description="View case information">
        <x-slot:actions>
            <a href="{{ route('compliance.cases.index') }}"><x-button variant="secondary">Back</x-button></a>
        </x-slot:actions>
    </x-page-header>

    <x-card title="Case Information">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <x-detail-row label="Case ID">{{ $case->id }}</x-detail-row>
            <x-detail-row label="Type">{{ $case->case_type?->value ?? $case->type }}</x-detail-row>
            <x-detail-row label="Subject">{{ $case->subject }}</x-detail-row>
            <x-detail-row label="Status" value-class=""><x-badge variant="warning">{{ $case->status }}</x-badge></x-detail-row>
            <x-detail-row label="Assignee">{{ $case->assignee?->name ?? 'Unassigned' }}</x-detail-row>
            <x-detail-row label="Created">{{ $case->created_at?->format('d M Y H:i') }}</x-detail-row>
        </dl>
    </x-card>

    <x-card title="Description" class="mt-6">
        <p class="text-sm text-ink">{{ $case->description ?? 'No description available.' }}</p>
    </x-card>
</x-app-layout>
