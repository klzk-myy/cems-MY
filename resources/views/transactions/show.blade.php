<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Details</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Page Header -->
        <div class="bg-white border-b border-[#e5e5e5]">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Transaction Details</h1>
                        <p class="mt-1 text-sm text-gray-500">ID: {{ $transaction['id'] ?? 'N/A' }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full {{ ($transaction['status'] ?? '') === 'Completed' ? 'bg-green-100 text-green-700' : (($transaction['status'] ?? '') === 'Pending' ? 'bg-yellow-100 text-yellow-700' : (($transaction['status'] ?? '') === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                            {{ $transaction['status'] ?? 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl mx-auto px-6 py-6 w-full">
            <!-- Transaction Info Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Transaction Information</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Transaction Type</label>
                            <p class="text-sm">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($transaction['type'] ?? '') === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $transaction['type'] ?? 'N/A' }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Foreign Currency</label>
                            <p class="text-sm text-gray-900">{{ $transaction['currency'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">FCY Amount</label>
                            <p class="text-sm font-medium text-gray-900">{{ $transaction['fcy_amount'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Exchange Rate</label>
                            <p class="text-sm text-gray-900">{{ $transaction['rate'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">MYR Amount</label>
                            <p class="text-sm font-medium text-gray-900">{{ $transaction['myr_amount'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Counter</label>
                            <p class="text-sm text-gray-900">{{ $transaction['counter_id'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Branch</label>
                            <p class="text-sm text-gray-900">{{ $transaction['branch_name'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Created By</label>
                            <p class="text-sm text-gray-900">{{ $transaction['created_by'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Created At</label>
                            <p class="text-sm text-gray-900">{{ $transaction['created_at'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Details Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Customer Details</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Customer Name</label>
                            <p class="text-sm text-gray-900">{{ $transaction['customer_name'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Customer ID</label>
                            <p class="text-sm text-gray-900">{{ $transaction['customer_id'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">ID Type</label>
                            <p class="text-sm text-gray-900">{{ $transaction['id_type'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">ID Number</label>
                            <p class="text-sm text-gray-900">{{ $transaction['id_number'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">CDD Level</label>
                            <p class="text-sm text-gray-900">{{ $transaction['cdd_level'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compliance Info Card -->
            @if(isset($transaction['compliance']))
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Compliance Information</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">CTOS Submitted</label>
                            <p class="text-sm text-gray-900">{{ $transaction['compliance']['ctos_submitted'] ?? 'No' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">STR Required</label>
                            <p class="text-sm text-gray-900">{{ $transaction['compliance']['str_required'] ?? 'No' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Risk Score</label>
                            <p class="text-sm text-gray-900">{{ $transaction['compliance']['risk_score'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Accounting Info Card -->
            @if(isset($transaction['accounting']))
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Accounting Entries</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Journal Entry ID</label>
                            <p class="text-sm text-gray-900">{{ $transaction['accounting']['journal_id'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Posted</label>
                            <p class="text-sm text-gray-900">{{ $transaction['accounting']['posted'] ?? 'No' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Period</label>
                            <p class="text-sm text-gray-900">{{ $transaction['accounting']['period'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Action Buttons Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl">
                <div class="p-6">
                    <div class="flex items-center gap-4 flex-wrap">
                        @if(($transaction['status'] ?? '') === 'Pending')
                            <a
                                href="{{ route('transactions.approve', $transaction['id'] ?? 0) }}"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                            >
                                Approve
                            </a>
                            <a
                                href="{{ route('transactions.reject', $transaction['id'] ?? 0) }}"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50"
                            >
                                Reject
                            </a>
                        @endif

                        @if(($transaction['status'] ?? '') === 'PendingCancellation')
                            <a
                                href="{{ route('transactions.approve-cancellation', $transaction['id'] ?? 0) }}"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700"
                            >
                                Approve Cancellation
                            </a>
                            <a
                                href="{{ route('transactions.reject-cancellation', $transaction['id'] ?? 0) }}"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700"
                            >
                                Reject Cancellation
                            </a>
                        @endif

                        @if(($transaction['status'] ?? '') === 'Completed')
                            <a
                                href="{{ route('transactions.cancel', $transaction['id'] ?? 0) }}"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-red-300 text-red-600 hover:bg-red-50"
                            >
                                Request Cancellation
                            </a>
                        @endif

                        <a
                            href="{{ route('transactions.print', $transaction['id'] ?? 0) }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50"
                        >
                            Print Receipt
                        </a>

                        <a
                            href="{{ route('transactions.index') }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50"
                        >
                            Back to List
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>