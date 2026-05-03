<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Transaction - Transactions</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Page Header -->
        <div class="bg-white border-b border-[#e5e5e5]">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <h1 class="text-2xl font-semibold text-gray-900">Confirm Transaction</h1>
                <p class="mt-1 text-sm text-gray-500">Review and confirm transaction details</p>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl mx-auto px-6 py-6 w-full">
            <!-- Transaction Summary Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Transaction Summary</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Transaction Type</label>
                            <p class="text-sm font-medium text-gray-900">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($transaction['type'] ?? '') === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $transaction['type'] ?? 'N/A' }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Amount</label>
                            <p class="text-sm font-medium text-gray-900">{{ $transaction['amount'] ?? 'N/A' }} {{ $transaction['currency'] ?? '' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Exchange Rate</label>
                            <p class="text-sm text-gray-900">{{ $transaction['rate'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Counter</label>
                            <p class="text-sm text-gray-900">{{ $transaction['counter_id'] ?? 'N/A' }}</p>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                    </div>
                </div>
            </div>

            <!-- Financial Details Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Financial Details</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">MYR Amount</label>
                            <p class="text-sm font-medium text-gray-900">{{ $transaction['myr_amount'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">FCY Amount</label>
                            <p class="text-sm font-medium text-gray-900">{{ $transaction['fcy_amount'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Spread</label>
                            <p class="text-sm text-gray-900">{{ $transaction['spread'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Total</label>
                            <p class="text-sm font-bold text-gray-900">{{ $transaction['total'] ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Form Card -->
            <div class="bg-white border border-[#e5e5e5] rounded-xl">
                <div class="px-6 py-4 border-b border-[#e5e5e5]">
                    <h2 class="text-lg font-medium text-gray-900">Confirm</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('transactions.confirm.store', $transaction['id'] ?? 0) }}">
                        @csrf
                        <div class="mb-4">
                            <label for="mfa_code" class="block text-sm font-medium text-gray-700 mb-2">
                                MFA Verification Code <span class="text-red-600">*</span>
                            </label>
                            <input
                                type="text"
                                id="mfa_code"
                                name="mfa_code"
                                required
                                maxlength="6"
                                pattern="[0-9]{6}"
                                class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg"
                                placeholder="Enter 6-digit MFA code"
                            >
                            @error('mfa_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-4">
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]"
                            >
                                Confirm Transaction
                            </button>
                            <a
                                href="{{ route('transactions.create') }}"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50"
                            >
                                Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>