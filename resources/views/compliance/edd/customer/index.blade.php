<x-app-layout title="My EDD Records">
    <div class="space-y-6">
        <x-page-header title="My EDD Records" description="Enhanced Due Diligence records and document requests" />

        @forelse($eddRecords as $record)
            <x-card title="EDD #{{ $record->edd_reference }} — {{ $record->status->label() }}">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div><span class="text-ink-muted">Risk Level:</span> <strong>{{ $record->risk_level?->value }}</strong></div>
                        <div><span class="text-ink-muted">Created:</span> {{ $record->created_at->format('d M Y') }}</div>
                        <div><span class="text-ink-muted">Status:</span> <x-badge>{{ $record->status->label() }}</x-badge></div>
                    </div>

                    @if($record->document_requests->isNotEmpty())
                        <div class="mt-4">
                            <h4 class="text-sm font-medium mb-2">Document Requests</h4>
                            <ul class="space-y-2">
                                @foreach($record->document_requests as $docReq)
                                    <li class="flex items-center justify-between border border-border rounded-lg p-3">
                                        <div>
                                            <span class="font-medium">{{ $docReq->document_type }}</span>
                    <x-badge variant="{{ $docReq->status === 'Received' ? 'success' : ($docReq->status === 'Rejected' ? 'danger' : 'warning') }}">
                                                 {{ $docReq->status->value }}
                                             </x-badge>
                                         </div>
                                         <div class="flex gap-2">
                                             @if($docReq->status === 'Pending')
                                                <form action="{{ route('compliance.edd.customer.upload', $docReq->id) }}" method="POST" enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required class="text-xs">
                                                    <x-button type="submit" variant="primary">Upload</x-button>
                                                </form>
                                            @endif
                                            @if($docReq->file_path)
                                                <x-button href="{{ route('compliance.edd.customer.download', $docReq->id) }}" variant="secondary">Download</x-button>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </x-card>
        @empty
            <x-card><p class="text-ink-muted text-center py-4">No EDD records found.</p></x-card>
        @endforelse

        <div class="mt-4">{{ $eddRecords->links() }}</div>
    </div>
</x-app-layout>
