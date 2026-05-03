<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sanctions Entry Details</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sanctions Entry Details</h1>
                    <p class="mt-1 text-sm text-gray-500">ENTRY-2024-001</p>
                </div>
                <div class="flex gap-3">
                    <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                        Back to List
                    </a>
                    <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                        Edit
                    </button>
                </div>
            </div>
        </div>

        <!-- Entry Details -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Entry Information</h3>
                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Entity Name</label>
                    <p class="text-sm text-gray-900">John Doe</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Entity Type</label>
                    <p class="text-sm text-gray-900">Individual</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">List Source</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">OFAC SDN</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Reference Number</label>
                    <p class="text-sm text-gray-900">OFAC-12345</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Nationality</label>
                    <p class="text-sm text-gray-900">United States</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Date Listed</label>
                    <p class="text-sm text-gray-900">2024-01-01</p>
                </div>
            </div>
        </div>

        <!-- Aliases -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Aliases</h3>
            <ul class="space-y-2">
                <li class="flex items-center gap-2 text-sm text-gray-900">
                    <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                    Johnny Doe
                </li>
                <li class="flex items-center gap-2 text-sm text-gray-900">
                    <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                    J. Doe
                </li>
            </ul>
        </div>

        <!-- Address -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Address</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Street</label>
                    <p class="text-sm text-gray-900">123 Main Street</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">City</label>
                    <p class="text-sm text-gray-900">New York</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Country</label>
                    <p class="text-sm text-gray-900">United States</p>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Additional Information</h3>
            <p class="text-sm text-gray-600">Added based on BNM advisory dated 2024-01-01. Subject to freeze of assets.</p>
        </div>

        <!-- Screening History -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Screening History</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Result</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-15</td>
                        <td class="px-4 py-3 text-sm text-gray-900">TXN-2024-001</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Match</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">Kuala Lumpur</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">2024-01-10</td>
                        <td class="px-4 py-3 text-sm text-gray-900">TXN-2024-045</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Potential Match</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">Penang</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Edit Entry
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    View Related Transactions
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Export
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-red-50 border border-red-200 text-red-700 hover:bg-red-100">
                    Deactivate
                </button>
            </div>
        </div>
    </div>
</body>
</html>