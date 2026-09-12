<x-app-layout title="STR Report">
    <div class="space-y-6">
        <x-page-header
            title="STR {{ $report->reference() }}"
            description="Suspicious Transaction Report detail (pd-00 section 22)"
            class="mb-8"
        >
            <x-slot:actions>
                <a href="{{ route('compliance.str.index') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-border bg-surface hover:bg-canvas-subtle">
                    Back to list
                </a>
            </x-slot:actions>
        </x-page-header>

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if(session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-card title="Report Detail" class="lg:col-span-2">

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Reference</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $report->reference() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Status</dt>
                        <dd class="mt-1">
                            <x-badge :variant="$report->status->color()">{{ $report->status->label() }}</x-badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Customer</dt>
                        <dd class="mt-1 text-sm text-ink">
                            @if ($report->customer)
                                <a href="{{ route('customers.show', $report->customer) }}" class="text-info hover:underline">
                                    {{ $report->customer->full_name }}
                                </a>
                                (ID #{{ $report->customer_id }})
                            @else
                                N/A
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Originating Case</dt>
                        <dd class="mt-1 text-sm text-ink">
                            @if ($report->case)
                                <a href="{{ route('compliance.cases.show', $report->case) }}" class="text-info hover:underline">
                                    {{ $report->case->case_number }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Trigger Amount (MYR)</dt>
                        <dd class="mt-1 text-sm font-semibold text-ink">RM {{ number_format((float) $report->trigger_amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">BNM Reference</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $report->bnm_reference ?? '-' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs font-medium text-ink-muted uppercase">Trigger Reason</dt>
                        <dd class="mt-1 text-sm text-ink whitespace-pre-line">{{ $report->trigger_reason }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Submitted At</dt>
                        <dd class="mt-1 text-sm text-ink">{{ optional($report->submitted_at)->format('d M Y H:i') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Acknowledged At</dt>
                        <dd class="mt-1 text-sm text-ink">{{ optional($report->acknowledged_at)->format('d M Y H:i') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Created By</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $report->createdBy?->username ?? 'System' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-ink-muted uppercase">Created At</dt>
                        <dd class="mt-1 text-sm text-ink">{{ optional($report->created_at)->format('d M Y H:i') }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Actions">

                @if ($report->status === \App\Enums\StrReportStatus::Draft)
                    <form method="POST" action="{{ route('compliance.str.submit', $report) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <x-input label="BNM Reference" name="bnm_reference" type="text" required />
                        <x-button variant="primary" type="submit">Submit to BNM</x-button>
                    </form>
                @elseif ($report->status === \App\Enums\StrReportStatus::Submitted)
                    <form method="POST" action="{{ route('compliance.str.acknowledge', $report) }}">
                        @csrf
                        @method('PATCH')
                        <p class="text-sm text-ink-muted mb-4">
                            Record BNM FIED acknowledgement of this filing.
                        </p>
                        <x-button variant="primary" type="submit">Mark Acknowledged</x-button>
                    </form>
                @else
                    <p class="text-sm text-ink-muted">
                        This report is {{ strtolower($report->status->label()) }}; no further actions are available.
                    </p>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
