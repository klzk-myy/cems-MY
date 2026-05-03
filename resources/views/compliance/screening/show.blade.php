<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Screening Result</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Screening Result</h1>
                    <p class="mt-1 text-sm text-gray-500">Transaction ID: TXN-2024-001</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Back
                </a>
            </div>
        </div>

        <!-- Transaction Details -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Transaction ID</label>
                    <p class="text-sm text-gray-900">TXN-2024-001</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Date</label>
                    <p class="text-sm text-gray-900">2024-01-15 10:30:00</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Type</label>
                    <p class="text-sm text-gray-900">Buy USD</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Amount</label>
                    <p class="text-sm text-gray-900">RM 28,000</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Customer</label>
                    <p class="text-sm text-gray-900">Ahmad Razali</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Counter</label>
                    <p class="text-sm text-gray-900">Counter 1 - KL Main</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Teller</label>
                    <p class="text-sm text-gray-900">Mike Tan</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Screening Status</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending Review</span>
                </div>
            </div>
        </div>

        <!-- Sanctions Screening Result -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Sanctions Screening</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900">Sanctions Check</h4>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Clear</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">OFAC SDN</span>
                            <span class="text-green-600">Clear</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">UN Security Council</span>
                            <span class="text-green-600">Clear</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">EU Sanctions</span>
                            <span class="text-green-600">Clear</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">BNM List</span>
                            <span class="text-green-600">Clear</span>
                        </div>
                    </div>
                </div>

                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900">AML Screening</h4>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Review</span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Velocity Check</span>
                            <span class="text-yellow-600">Flagged</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Structuring Check</span>
                            <span class="text-yellow-600">Flagged</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">PEP Check</span>
                            <span class="text-green-600">Clear</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Adverse Media</span>
                            <span class="text-green-600">Clear</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Indicators -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk Indicators</h3>
            <div class="space-y-4">
                <div class="flex items-center gap-4 p-3 bg-yellow-50 rounded-lg">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-900">High Transaction Velocity</p>
                        <p class="text-xs text-yellow-700">Customer has conducted 5 transactions totaling RM 45,000 in the last 7 days</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-3 bg-yellow-50 rounded-lg">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-900">Approaching STR Threshold</p>
                        <p class="text-xs text-yellow-700">Transaction plus recent activity approaches RM 50,000 STR threshold</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Approve Transaction
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Hold for Review
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Create Alert
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    View Customer Profile
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-red-50 border border-red-200 text-red-700 hover:bg-red-100">
                    Reject Transaction
                </button>
            </div>
        </div>
    </div>
</body>
</html>