<x-app-layout title="Edit Report Schedule">
    <div class="space-y-6">
        <x-page-header title="Edit Report Schedule" description="Update the schedule for {{ $schedule->report_type?->label() ?? $schedule->report_type?->value }}">
            <x-slot:actions>
                <x-button href="{{ route('reports.schedules.show', $schedule) }}" variant="secondary">Back to Schedule</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('reports.schedules.partials._form', ['reportTypes' => $reportTypes])
    </div>
</x-app-layout>
