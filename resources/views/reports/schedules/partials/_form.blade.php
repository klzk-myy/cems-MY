@php
    $isEdit = isset($schedule);
    $action = $isEdit ? route('reports.schedules.update', $schedule) : route('reports.schedules.store');
@endphp

<x-card>
    <form action="{{ $action }}" method="POST" class="space-y-4">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-select
            label="Report Type"
            name="report_type"
            :options="\App\Models\ReportSchedule::getReportTypes()"
            :selected="old('report_type', $schedule?->report_type?->value)"
            required
            help="The regulatory report this schedule generates."
        />

        <x-input
            label="Cron Expression"
            name="cron_expression"
            type="text"
            :value="old('cron_expression', $schedule?->cron_expression)"
            required
            placeholder="0 0 * * *"
            help="Standard 5-field cron (minute hour day month weekday). Examples: 0 0 * * * (daily midnight), 0 0 1 * * (monthly on the 1st), 0 0 1 1,4,7,10 * (quarterly)."
        />

        <x-textarea
            label="Parameters (JSON)"
            name="parameters"
            rows="4"
            help='Optional JSON object overriding the default period. Keys: "date" (Y-m-d) for MSB2, "month" (Y-m) for LMCA, "quarter" (Y-Qn or Y-m-d) for QLVR, e.g. {"month": "2026-07"}.'
        >{{ old('parameters', $schedule?->parameters ? json_encode($schedule->parameters, JSON_PRETTY_PRINT) : '') }}</x-textarea>

        <x-checkbox
            label="Active"
            name="is_active"
            value="1"
            :checked="(bool) old('is_active', $schedule?->is_active ?? true)"
            help="Inactive schedules are skipped by the hourly processor."
        />

        <x-textarea
            label="Notification Recipients"
            name="notification_recipients"
            rows="3"
            placeholder="compliance@example.com"
            help="Email addresses to notify after each run — one per line or comma-separated."
        >{{ old('notification_recipients', isset($schedule) ? implode("\n", $schedule->notification_recipients ?? []) : '') }}</x-textarea>

        <div class="flex gap-2 pt-2">
            <x-button type="submit" variant="primary">{{ $isEdit ? 'Update Schedule' : 'Create Schedule' }}</x-button>
            <x-button href="{{ $isEdit ? route('reports.schedules.show', $schedule) : route('reports.schedules.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-card>
