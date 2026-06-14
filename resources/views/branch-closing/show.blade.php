<x-app-layout title="Branch Closure - {{ $branch->name }}">
    <div class="p-6 space-y-6">
        <x-page-header title="Branch Closure: {{ $branch->name }}" :actions="true">
            <x-slot:actions>
                @if($workflow)
                    <x-badge variant="warning">{{ ucfirst($workflow->status) }}</x-badge>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if(session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        @if($workflow)
            <x-card title="Workflow Progress">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-ink-muted">Initiated</p>
                        <p class="text-sm font-medium text-ink">{{ $workflow->initiated_at ? $workflow->initiated_at->format('Y-m-d H:i') : '-' }}</p>
                    </div>
                    @if($workflow->settled_at)
                    <div>
                        <p class="text-sm text-ink-muted">Settled</p>
                        <p class="text-sm font-medium text-ink">{{ $workflow->settled_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                    @if($workflow->finalized_at)
                    <div>
                        <p class="text-sm text-ink-muted">Finalized</p>
                        <p class="text-sm font-medium text-ink">{{ $workflow->finalized_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                </div>
            </x-card>

            <x-card title="Checklist">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-canvas-subtle rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['counters_closed'])
                                <svg class="w-5 h-5 text-success-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 text-danger-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            <span class="text-sm font-medium text-ink">Counters Closed</span>
                        </div>
                        <span class="text-sm {{ $checklist['counters_closed'] ? 'text-success-text' : 'text-danger-text' }}">
                            {{ $checklist['counters_closed'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-canvas-subtle rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['allocations_returned'])
                                <svg class="w-5 h-5 text-success-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 text-danger-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            <span class="text-sm font-medium text-ink">Teller Allocations Returned</span>
                        </div>
                        <span class="text-sm {{ $checklist['allocations_returned'] ? 'text-success-text' : 'text-danger-text' }}">
                            {{ $checklist['allocations_returned'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-canvas-subtle rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['transfers_complete'])
                                <svg class="w-5 h-5 text-success-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 text-danger-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            <span class="text-sm font-medium text-ink">Transfers Complete</span>
                        </div>
                        <span class="text-sm {{ $checklist['transfers_complete'] ? 'text-success-text' : 'text-danger-text' }}">
                            {{ $checklist['transfers_complete'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-canvas-subtle rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['documents_finalized'])
                                <svg class="w-5 h-5 text-success-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 text-danger-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            <span class="text-sm font-medium text-ink">Documents Finalized</span>
                        </div>
                        <span class="text-sm {{ $checklist['documents_finalized'] ? 'text-success-text' : 'text-danger-text' }}">
                            {{ $checklist['documents_finalized'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-between">
                @if($workflow->status === 'initiated')
                    <form method="POST" action="{{ route('branch-closing.settle', $branch) }}">
                        @csrf
                        <x-button variant="primary" type="submit">Mark as Settled</x-button>
                    </form>
                @endif

                @if($canFinalize)
                    <form method="POST" action="{{ route('branch-closing.finalize', $branch) }}">
                        @csrf
                        <x-button variant="primary" type="submit">Finalize Closure</x-button>
                    </form>
                @else
                    <p class="text-sm text-ink-muted">Complete all checklist items to finalize.</p>
                @endif
            </div>
        @else
            <x-empty-state message="No active closure workflow for this branch.">
                <x-slot:actions>
                    <form method="POST" action="{{ route('branch-closing.initiate', $branch) }}" class="inline">
                        @csrf
                        <x-button variant="primary" type="submit">Initiate Closure Workflow</x-button>
                    </form>
                </x-slot:actions>
            </x-empty-state>
        @endif
    </div>
</x-app-layout>
