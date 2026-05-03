<x-app-layout title="STR Details - {{ $str->str_number }}">
    <div class="flex min-h-screen flex-col">
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">STR: {{ $str->str_number }}</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            @if($str->customer)
                                {{ $str->customer->name }} ({{ $str->customer->id_number }})
                            @else
                                No customer linked
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full
                            @switch($str->status->value)
                                @case('draft') bg-gray-100 text-gray-700
                                @case('pending_review') bg-yellow-100 text-yellow-700
                                @case('pending_approval') bg-blue-100 text-blue-700
                                @case('submitted') bg-green-100 text-green-700
                                @case('acknowledged') bg-green-100 text-green-700
                                @default bg-gray-100 text-gray-700
                            @endswitch">
                            {{ ucfirst(str_replace('_', ' ', $str->status->value)) }}
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
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

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- STR Details Card -->
                        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">STR Information</h2>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm text-gray-500">STR Number</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $str->str_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Report Date</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $str->report_date?->format('Y-m-d') ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Prepared By</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $str->creator?->username ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Reviewed By</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $str->reviewer?->username ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Approved By</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $str->approver?->username ?? '-' }}</dd>
                                </div>
                                @if($str->bnm_reference)
                                <div>
                                    <dt class="text-sm text-gray-500">BNM Reference</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $str->bnm_reference }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>

                        <!-- Customer Information Card -->
                        @if($str->customer)
                        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Customer Information</h2>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm text-gray-500">Name</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $str->customer->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">ID Number</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $str->customer->id_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Risk Rating</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                                            @switch($str->customer->risk_rating?->value)
                                                @case('high') bg-red-100 text-red-700
                                                @case('medium') bg-yellow-100 text-yellow-700
                                                @case('low') bg-green-100 text-green-700
                                                @default bg-gray-100 text-gray-700
                                            @endswitch">
                                            {{ ucfirst($str->customer->risk_rating?->value ?? 'unknown') }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        @endif

                        <!-- Linked Transactions Card -->
                        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Linked Transactions</h2>
                            @if($transactions->isNotEmpty())
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Currency</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach($transactions as $tx)
                                            <tr>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $tx->transaction_date?->format('Y-m-d') ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $tx->type->value === 'buy' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                                        {{ ucfirst($tx->type->value) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format((float)$tx->amount, 2) }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $tx->currency_code }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ $tx->status->value }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="text-sm text-gray-500">No transactions linked to this STR.</p>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-6">
                        <!-- Actions Card -->
                        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Actions</h2>
                            <div class="space-y-3">
                                @if($str->isDraft())
                                    <form method="POST" action="{{ route('str.submit-for-review', $str) }}">
                                        @csrf
                                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                            Submit for Review
                                        </button>
                                    </form>
                                    <a href="{{ route('str.edit', $str) }}" class="block text-center px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                                        Edit STR
                                    </a>
                                @endif

                                @if($str->isPendingReview())
                                    <form method="POST" action="{{ route('str.submit-for-approval', $str) }}">
                                        @csrf
                                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                            Submit for Approval
                                        </button>
                                    </form>
                                @endif

                                @if($str->isPendingApproval())
                                    <form method="POST" action="{{ route('str.approve', $str) }}">
                                        @csrf
                                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700">
                                            Approve STR
                                        </button>
                                    </form>
                                @endif

                                @if($str->status->canSubmit())
                                    <form method="POST" action="{{ route('str.submit', $str) }}">
                                        @csrf
                                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                            Submit to goAML
                                        </button>
                                    </form>
                                @endif

                                @if($str->status->value === 'submitted')
                                    <div class="p-4 bg-green-50 rounded-lg">
                                        <p class="text-sm text-green-700">STR has been submitted to goAML and is awaiting acknowledgment.</p>
                                    </div>
                                @endif

                                @if($str->status->value === 'submitted' || $str->status->value === 'acknowledged')
                                    <form method="GET" action="{{ route('str.track-acknowledgment', $str) }}" class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Track BNM Acknowledgment</label>
                                        <div class="flex gap-2">
                                            <input type="text" name="bnm_reference" placeholder="BNM Reference" class="flex-1 w-full px-3 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                                            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">
                                                Track
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <!-- Workflow Timeline Card -->
                        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Workflow Timeline</h2>
                            <div class="space-y-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full {{ $str->created_at ? 'bg-green-500' : 'bg-gray-300' }}"></div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Created</p>
                                        <p class="text-xs text-gray-500">{{ $str->created_at?->format('Y-m-d H:i') ?? '-' }}</p>
                                    </div>
                                </div>
                                @if($str->reviewed_at)
                                <div class="flex items-start gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-yellow-500"></div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Reviewed</p>
                                        <p class="text-xs text-gray-500">{{ $str->reviewed_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($str->approved_at)
                                <div class="flex items-start gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-blue-500"></div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Approved</p>
                                        <p class="text-xs text-gray-500">{{ $str->approved_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($str->submitted_at)
                                <div class="flex items-start gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-green-500"></div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Submitted to goAML</p>
                                        <p class="text-xs text-gray-500">{{ $str->submitted_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</main>
</x-app-layout>