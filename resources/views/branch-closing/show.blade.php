<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Closing - {{ $branch->code }} - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <aside class="w-60 bg-white border-r border-[#e5e5e5] flex flex-col shrink-0">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h1 class="text-lg font-semibold text-[#171717]">CEMS-MY</h1>
            </div>
            <nav class="flex-1 p-4 space-y-6 overflow-y-auto">
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Main</div>
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Dashboard</a>
                    <a href="{{ route('transactions.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Transactions</a>
                    <a href="{{ route('counters.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Counters</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Management</div>
                    <a href="{{ route('customers.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Customers</a>
                    <a href="{{ route('compliance') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Compliance</a>
                    <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Reports</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">System</div>
                    <a href="{{ route('users.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Users</a>
                    <a href="{{ route('rates.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Rates</a>
                    <a href="{{ route('accounting.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Accounting</a>
                </div>
            </nav>
        </aside>
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('branches.show', $branch) }}" class="text-[#6b6b6b] hover:text-[#171717]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <h1 class="text-2xl font-semibold text-[#171717]">Branch Closing Workflow</h1>
                    </div>
                    <p class="text-sm text-[#6b6b6b] mt-1">{{ $branch->code }} - {{ $branch->name }}</p>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            @if($workflow)
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-[#171717]">Workflow Status</h2>
                        <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full
                            @if($workflow->isInitiated()) bg-yellow-100 text-yellow-700
                            @elseif($workflow->isSettled()) bg-blue-100 text-blue-700
                            @else bg-green-100 text-green-700
                            @endif">
                            {{ ucfirst($workflow->status) }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-[#6b6b6b]">Initiated by:</span>
                            <span class="ml-2 text-[#171717]">{{ $workflow->initiator->username ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-[#6b6b6b]">Initiated at:</span>
                            <span class="ml-2 text-[#171717]">{{ $workflow->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                        @if($workflow->settlement_at)
                        <div>
                            <span class="text-[#6b6b6b]">Settled at:</span>
                            <span class="ml-2 text-[#171717]">{{ $workflow->settlement_at->format('Y-m-d H:i') }}</span>
                        </div>
                        @endif
                        @if($workflow->finalized_at)
                        <div>
                            <span class="text-[#6b6b6b]">Finalized at:</span>
                            <span class="ml-2 text-[#171717]">{{ $workflow->finalized_at->format('Y-m-d H:i') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                    <h2 class="text-lg font-semibold text-[#171717] mb-4">Closing Checklist</h2>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-4 bg-[#f7f7f8] rounded-lg">
                            <div class="flex items-center gap-3">
                                @if($checklist['counters_closed'])
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                @endif
                                <span class="text-sm font-medium text-[#171717]">Counters Closed</span>
                            </div>
                            <span class="text-sm @if($checklist['counters_closed']) text-green-600 @else text-red-600 @endif">
                                {{ $checklist['counters_closed'] ? 'Complete' : 'Pending' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-[#f7f7f8] rounded-lg">
                            <div class="flex items-center gap-3">
                                @if($checklist['allocations_returned'])
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                @endif
                                <span class="text-sm font-medium text-[#171717]">Allocations Returned to Pool</span>
                            </div>
                            <span class="text-sm @if($checklist['allocations_returned']) text-green-600 @else text-red-600 @endif">
                                {{ $checklist['allocations_returned'] ? 'Complete' : 'Pending' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-[#f7f7f8] rounded-lg">
                            <div class="flex items-center gap-3">
                                @if($checklist['transfers_complete'])
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                @endif
                                <span class="text-sm font-medium text-[#171717]">Transfers Complete</span>
                            </div>
                            <span class="text-sm @if($checklist['transfers_complete']) text-green-600 @else text-red-600 @endif">
                                {{ $checklist['transfers_complete'] ? 'Complete' : 'Pending' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-[#f7f7f8] rounded-lg">
                            <div class="flex items-center gap-3">
                                @if($checklist['documents_finalized'])
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                @endif
                                <span class="text-sm font-medium text-[#171717]">Documents Finalized</span>
                            </div>
                            <span class="text-sm @if($checklist['documents_finalized']) text-green-600 @else text-red-600 @endif">
                                {{ $checklist['documents_finalized'] ? 'Complete' : 'Pending' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    @if(!$workflow->isFinalized())
                        @if($canFinalize)
                            <form method="POST" action="{{ route('branch-closing.finalize', $branch) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">
                                    Finalize Branch Closing
                                </button>
                            </form>
                        @else
                            <span class="px-4 py-2 text-sm text-[#6b6b6b] bg-[#e5e5e5] rounded-lg">
                                Complete all checklist items to finalize
                            </span>
                        @endif
                    @else
                        <span class="px-4 py-2 text-sm text-green-700 bg-green-100 rounded-lg">
                            Branch Closing Finalized
                        </span>
                    @endif
                </div>
            @else
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                    <p class="text-[#6b6b6b] text-center py-8">No active closure workflow for this branch.</p>
                    <form method="POST" action="{{ route('branch-closing.initiate', $branch) }}">
                        @csrf
                        <div class="flex justify-center">
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">
                                Initiate Branch Closing
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </main>
    </div>
</body>
</html>