<x-app-layout title="Branch Closure - {{ $branch->name }}">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Branch Closure: {{ $branch->name }}</h1>
            @if($workflow)
                <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full bg-yellow-100 text-yellow-800">
                    {{ ucfirst($workflow->status) }}
                </span>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-lg bg-green-50 p-4 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        @if($workflow)
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Workflow Progress</h2>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-500">Initiated</p>
                        <p class="text-sm font-medium text-gray-900">{{ $workflow->initiated_at ? $workflow->initiated_at->format('Y-m-d H:i') : '-' }}</p>
                    </div>
                    @if($workflow->settled_at)
                    <div>
                        <p class="text-sm text-gray-500">Settled</p>
                        <p class="text-sm font-medium text-gray-900">{{ $workflow->settled_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                    @if($workflow->finalized_at)
                    <div>
                        <p class="text-sm text-gray-500">Finalized</p>
                        <p class="text-sm font-medium text-gray-900">{{ $workflow->finalized_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Checklist</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['counters_closed'])
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            <span class="text-sm font-medium text-gray-900">Counters Closed</span>
                        </div>
                        <span class="text-sm {{ $checklist['counters_closed'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $checklist['counters_closed'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['allocations_returned'])
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            <span class="text-sm font-medium text-gray-900">Teller Allocations Returned</span>
                        </div>
                        <span class="text-sm {{ $checklist['allocations_returned'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $checklist['allocations_returned'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['transfers_complete'])
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            <span class="text-sm font-medium text-gray-900">Transfers Complete</span>
                        </div>
                        <span class="text-sm {{ $checklist['transfers_complete'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $checklist['transfers_complete'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($checklist['documents_finalized'])
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            <span class="text-sm font-medium text-gray-900">Documents Finalized</span>
                        </div>
                        <span class="text-sm {{ $checklist['documents_finalized'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $checklist['documents_finalized'] ? 'Complete' : 'Pending' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                @if($workflow->status === 'initiated')
                    <form method="POST" action="{{ route('branch-closing.settle', $branch) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                            Mark as Settled
                        </button>
                    </form>
                @endif

                @if($canFinalize)
                    <form method="POST" action="{{ route('branch-closing.finalize', $branch) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                            Finalize Closure
                        </button>
                    </form>
                @else
                    <p class="text-sm text-gray-500">Complete all checklist items to finalize.</p>
                @endif
            </div>
        @else
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-8 text-center">
                <p class="text-gray-600 mb-4">No active closure workflow for this branch.</p>
                <form method="POST" action="{{ route('branch-closing.initiate', $branch) }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Initiate Closure Workflow
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>