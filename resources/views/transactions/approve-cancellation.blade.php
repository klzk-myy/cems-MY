<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Cancellation - Transactions</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Page Header -->
        <div class="bg-white border-b border-[#e5e5e5]">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <h1 class="text-2xl font-semibold text-gray-900">Approve Cancellation</h1>
                <p class="mt-1 text-sm text-gray-500">Review and approve transaction cancellation request</p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl mx-auto px-6 py-6 w-full">
            <!-- Transaction Details Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Transaction Details</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Transaction ID</label>
                            <p class="text-sm text-gray-900">{{ $transaction['id'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Transaction Type</label>
                            <p class="text-sm text-gray-900">{{ $transaction['type'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Amount</label>
                            <p class="text-sm text-gray-900">{{ $transaction['amount'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Currency</label>
                            <p class="text-sm text-gray-900">{{ $transaction['currency'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Customer</label>
                            <p class="text-sm text-gray-900">{{ $transaction['customer_name'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Original Date</label>
                            <p class="text-sm text-gray-900">{{ $transaction['created_at'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancellation Reason Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Cancellation Reason</h2>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-700">{{ $cancellation['reason'] ?? 'No reason provided' }}</p>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Requested By</label>
                        <p class="text-sm text-gray-900">{{ $cancellation['requested_by'] ?? 'N/A' }}</p>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Requested At</label>
                        <p class="text-sm text-gray-900">{{ $cancellation['requested_at'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- Approval Form Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Manager Approval</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('transactions.approve-cancellation.store', $transaction['id'] ?? 0) }}">
                        @csrf
                        <div class="mb-4">
                            <label for="approval_notes" class="block text-sm font-medium text-gray-700 mb-2">Approval Notes</label>
                            <textarea
                                id="approval_notes"
                                name="approval_notes"
                                rows="4"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg"
                                placeholder="Enter approval notes (optional)"
                            ></textarea>
                        </div>
                        <div class="flex items-center gap-4">
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                            >
                                Approve Cancellation
                            </button>
                            <a
                                href="{{ route('transactions.index') }}"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50"
                            >
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>