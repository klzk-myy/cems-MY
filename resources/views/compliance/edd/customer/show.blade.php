<x-app-layout title="My EDD Record">
    <div class="space-y-6">
        <x-page-header title="EDD Record {{ $eddRecord->edd_reference }}">
            Enhanced Due Diligence record and document requests
            <x-slot:actions>
                <x-button variant="secondary" href="{{ url()->previous() }}">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Customer Details">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Name</label>
                    <p class="text-ink">{{ $customer?->full_name ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">ID Number</label>
                    <p class="text-ink font-mono">{{ $customer?->id_number_masked ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Nationality</label>
                    <p class="text-ink">{{ $customer?->nationality ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Phone</label>
                    <p class="text-ink">{{ $customer?->phone ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Email</label>
                    <p class="text-ink">{{ $customer?->email ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Risk Rating</label>
                    <p class="text-ink">
                        <x-badge variant="{{ $customer?->risk_variant ?? 'gray' }}">{{ $customer?->risk_rating ?? '-' }}</x-badge>
                    </p>
                </div>
            </div>
        </x-card>

        @php
            $eddStatusVariant = match ($eddRecord->status->value) {
                'Approved' => 'success',
                'Rejected', 'Expired' => 'danger',
                'Pending_Review' => 'warning',
                default => 'info',
            };
        @endphp

        <x-card title="EDD Record">
            <x-slot:actions>
                <x-badge variant="{{ $eddStatusVariant }}" size="lg">{{ $eddRecord->status->label() }}</x-badge>
            </x-slot:actions>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Reference</label>
                    <p class="font-mono text-ink">{{ $eddRecord->edd_reference }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Risk Level</label>
                    <p class="text-ink">{{ $eddRecord->risk_level?->value ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Employment Status</label>
                    <p class="text-ink">{{ $eddRecord->employment_status?->value ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Source of Funds</label>
                    <p class="text-ink">{{ $eddRecord->source_of_funds ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Purpose of Transaction</label>
                    <p class="text-ink">{{ $eddRecord->purpose_of_transaction ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Estimated Net Worth</label>
                    <p class="text-ink">{{ $eddRecord->estimated_net_worth ?? '-' }}</p>
                </div>
            </div>

            <ol class="mt-6 pt-6 border-t border-border space-y-4">
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 bg-success"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">Record Created</p>
                        <p class="text-ink-muted">{{ $eddRecord->created_at?->format('Y-m-d H:i') }}</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $eddRecord->questionnaire_completed_at ? 'bg-success' : 'bg-canvas-subtle' }}"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">Questionnaire Completed</p>
                        <p class="text-ink-muted">{{ $eddRecord->questionnaire_completed_at?->format('Y-m-d H:i') ?? 'Pending' }}</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $eddRecord->reviewed_at ? 'bg-success' : 'bg-canvas-subtle' }}"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">Reviewed</p>
                        <p class="text-ink-muted">{{ $eddRecord->reviewed_at?->format('Y-m-d H:i') ?? 'Pending' }}</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $eddRecord->approved_at ? 'bg-success' : 'bg-canvas-subtle' }}"></span>
                    <div class="text-sm">
                        <p class="font-medium text-ink">Decision</p>
                        <p class="text-ink-muted">{{ $eddRecord->approved_at?->format('Y-m-d H:i') ?? 'Pending' }}</p>
                    </div>
                </li>
            </ol>

            @if($eddRecord->review_notes)
                <div class="mt-4 pt-4 border-t border-border">
                    <label class="block text-xs font-medium text-ink-muted uppercase mb-1">Review Notes</label>
                    <p class="text-sm text-ink">{{ $eddRecord->review_notes }}</p>
                </div>
            @endif
        </x-card>

        @if($eddRecord->questionnaire_responses)
            <x-card title="Questionnaire Responses">
                <dl class="space-y-3">
                    @foreach($eddRecord->questionnaire_responses as $question => $answer)
                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-2 border-b border-border pb-3 last:border-0 last:pb-0">
                            <dt class="text-sm font-medium text-ink md:w-1/2">{{ $question }}</dt>
                            <dd class="text-sm text-ink-muted md:w-1/2 md:text-right">{{ is_array($answer) ? implode(', ', $answer) : $answer }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        @endif

        <x-card title="Document Requests">
            @if($eddRecord->documentRequests->isNotEmpty())
                <ul class="space-y-2">
                    @foreach($eddRecord->documentRequests as $docReq)
                        <li class="flex flex-col md:flex-row md:items-center justify-between gap-3 border border-border rounded-lg p-3">
                            <div class="space-y-1">
                                <span class="font-medium text-ink">{{ $docReq->document_type }}</span>
                                <x-badge variant="{{ $docReq->status->value === 'Verified' ? 'success' : ($docReq->status->value === 'Rejected' ? 'danger' : ($docReq->status->value === 'Received' ? 'info' : 'warning')) }}">
                                    {{ $docReq->status->label() }}
                                </x-badge>
                                @if($docReq->rejection_reason)
                                    <p class="text-sm text-danger-text">Reason: {{ $docReq->rejection_reason }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if($docReq->status->value === 'Pending')
                                    <form action="{{ route('compliance.edd.customer.upload', $docReq->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                                        @csrf
                                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required class="text-xs">
                                        <x-button type="submit" variant="primary" size="sm">Upload</x-button>
                                    </form>
                                @endif
                                @if($docReq->file_path)
                                    <x-button href="{{ route('compliance.edd.customer.download', $docReq->id) }}" variant="secondary" size="sm">Download</x-button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-ink-muted text-center py-4">No document requests for this record.</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
