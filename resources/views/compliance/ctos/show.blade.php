<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTOS Report Details - CTOS-2024-01</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">CTOS Report Details</h1>
                    <p class="mt-1 text-sm text-gray-500">CTOS-2024-01</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Report Summary -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Period</label>
                    <p class="text-sm text-gray-900">January 2024</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Branch</label>
                    <p class="text-sm text-gray-900">Kuala Lumpur</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Status</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Submitted</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Submitted At</label>
                    <p class="text-sm text-gray-900">2024-02-05 14:30:00</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Total Transactions</label>
                    <p class="text-sm text-gray-900">156</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Total Amount</label>
                    <p class="text-sm text-gray-900">RM 4,250,000</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Submitted By</label>
                    <p class="text-sm text-gray-900">Jane Doe</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">BNM Reference</label>
                    <p class="text-sm text-gray-900">BNM/CTOS/2024/02/001</p>
                </div>
            </div>
        </div>

        <!-- Transaction Breakdown -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Breakdown by Type</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Buy USD</label>
                    <p class="text-2xl font-bold text-gray-900">45</p>
                    <p class="text-sm text-gray-500">RM 1,200,000</p>
                </div>
                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Sell USD</label>
                    <p class="text-2xl font-bold text-gray-900">62</p>
                    <p class="text-sm text-gray-500">RM 1,850,000</p>
                </div>
                <div class="border border-[#e5e5e5] rounded-lg p-4">
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Other Currencies</label>
                    <p class="text-2xl font-bold text-gray-900">49</p>
                    <p class="text-sm text-gray-500">RM 1,200,000</p>
                </div>
            </div>
        </div>

        <!-- Transaction List -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Details</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Currency</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">TXN-2024-001</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-05</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Buy</td>
                        <td class="px-4 py-3 text-sm text-gray-900">USD</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 28,000</td>
                        <td class="px-4 py-3 text-sm text-gray-900">CUST-001</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">TXN-2024-002</td>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-06</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Sell</td>
                        <td class="px-4 py-3 text-sm text-gray-900">EUR</td>
                        <td class="px-4 py-3 text-sm text-gray-900">RM 35,000</td>
                        <td class="px-4 py-3 text-sm text-gray-900">CUST-002</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Download PDF
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Print Report
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Resubmit to BNM
                </button>
            </div>
        </div>
    </div>
</body>
</html>