<x-app-layout title="Create Report Schedule">
    <div class="space-y-6">
        <x-page-header title="Create Report Schedule" description="Define an automated generation schedule for a regulatory report">
            <x-slot:actions>
                <x-button href="{{ route('reports.schedules.index') }}" variant="secondary">Back to Schedules</x-button>
            </x-slot:actions>
        </x-page-header>

        @include('reports.schedules.partials._form', ['reportTypes' => $reportTypes])
    </div>
</x-app-layout>
