<x-app-layout title="Screening Status">
    <div class="space-y-6">
        <x-page-header
            title="Screening Status"
            description="Current sanctions screening status for {{ $customer->full_name }}"
        />

        <x-card>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Sanctions Hit</label>
                    <x-badge :variant="$status['sanction_hit'] ? 'danger' : 'success'">
                        {{ $status['sanction_hit'] ? 'Yes' : 'No' }}
                    </x-badge>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Last Result</label>
                    <p class="text-sm text-ink">
                        {{ $status['last_result'] ? ucfirst(str_replace('_', ' ', (string) $status['last_result'])) : 'Never screened' }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Last Screened At</label>
                    <p class="text-sm text-ink">{{ $status['last_screened_at'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Last Match Score</label>
                    <p class="text-sm text-ink">
                        {{ $status['last_match_score'] !== null ? round((float) $status['last_match_score'], 1).'%' : 'N/A' }}
                    </p>
                </div>
            </div>
        </x-card>

        <x-button variant="secondary" href="{{ url()->previous() }}">Back</x-button>
    </div>
</x-app-layout>
