<x-app-layout title="EDD Review {{ $record->edd_reference }}">
    <div class="space-y-6">
        <x-page-header title="EDD Review: {{ $record->edd_reference }}" description="Enhanced Due Diligence record detail" />

        @if(session('error'))
            <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-200">
                <ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <x-card title="Record Details">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><span class="text-ink-muted">Status:</span> <x-badge>{{ $record->status->label() }}</x-badge></div>
                <div><span class="text-ink-muted">Risk Level:</span> {{ $record->risk_level?->value }}</div>
                <div>
                    <span class="text-ink-muted">Customer:</span>
                    @if($record->customer)
                        {{ $record->customer->full_name ?? $record->customer->name ?? 'Customer #'.$record->customer->id }}
                        @if($portalUrl)
                            — <a href="{{ $portalUrl }}" class="text-primary hover:underline" target="_blank" rel="noopener">open customer portal</a>
                        @endif
                    @else
                        —
                    @endif
                </div>
                <div>
                    <span class="text-ink-muted">Flagged Transaction:</span>
                    @if($record->flaggedTransaction)
                        #{{ $record->flaggedTransaction->id }} ({{ $record->flaggedTransaction->transaction_ref ?? 'ref n/a' }})
                    @else
                        —
                    @endif
                </div>
                <div><span class="text-ink-muted">Questionnaire Completed:</span> {{ $record->questionnaire_completed_at?->format('d M Y H:i') ?? '—' }}</div>
                <div><span class="text-ink-muted">Created:</span> {{ $record->created_at->format('d M Y H:i') }}</div>
            </div>

            @if($record->questionnaire_responses)
                <div class="mt-6">
                    <h4 class="text-sm font-medium mb-2">Questionnaire Responses</h4>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 border border-border rounded-lg p-4 text-sm">
                        @foreach($record->questionnaire_responses as $key => $value)
                            <div><dt class="inline font-medium">{{ ucfirst(str_replace('_', ' ', (string) $key)) }}:</dt>
                                <dd class="inline ml-1">{{ is_array($value) ? implode(', ', $value) : $value }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endif

            @if($record->review_notes)
                <div class="mt-6">
                    <h4 class="text-sm font-medium mb-2">Review Notes</h4>
                    <p class="border border-border rounded-lg p-4 text-sm whitespace-pre-line">{{ $record->review_notes }}</p>
                </div>
            @endif
        </x-card>

        @if($isFinalisable)
            <x-card title="Decision">
                <div class="space-y-6">
                    <form action="{{ route('compliance.edd-reviews.approve', $record) }}" method="POST">
                        @csrf
                        <x-button type="submit" variant="primary">Approve</x-button>
                    </form>

                    <form action="{{ route('compliance.edd-reviews.reject', $record) }}" method="POST" class="space-y-3">
                        @csrf
                        <label for="reason" class="block text-sm font-medium">Rejection reason <span class="text-red-600">*</span></label>
                        <x-textarea name="reason" id="reason" rows="3" required maxlength="1000"
                            placeholder="Explain why this EDD record is rejected (required)" />
                        <x-button type="submit" variant="secondary">Reject</x-button>
                    </form>
                </div>
            </x-card>
        @else
            <x-card>
                <p class="text-sm text-ink-muted">
                    This record cannot be finalised in its current status ({{ $record->status->value }}).
                    Only records with a submitted questionnaire or in Pending Review can be approved or rejected.
                </p>
            </x-card>
        @endif

        <div>
            <x-button href="{{ route('compliance.edd-reviews.index') }}" variant="secondary" size="sm">Back to list</x-button>
        </div>
    </div>
</x-app-layout>
