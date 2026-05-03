<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Details - CASE-2024-001</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Case Details</h1>
                    <p class="mt-1 text-sm text-gray-500">CASE-2024-001</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Case Overview -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Priority</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Critical</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Status</label>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">In Progress</span>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Customer</label>
                    <p class="text-sm text-gray-900">Ahmad Razali</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Assigned To</label>
                    <p class="text-sm text-gray-900">John Smith</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Created</label>
                    <p class="text-sm text-gray-900">2024-01-15 09:30:00</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Due Date</label>
                    <p class="text-sm text-gray-900">2024-01-22</p>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Description</h3>
            <p class="text-sm text-gray-600">Investigation into suspicious transaction patterns exhibited by this customer over the past 30 days. Multiple transactions below the STR threshold have been identified.</p>
        </div>

        <!-- Timeline -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Case Timeline</h3>
            <div class="space-y-4">
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-blue-500"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Case created</p>
                        <p class="text-xs text-gray-500">2024-01-15 09:30:00 by John Smith</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-yellow-500"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Assigned to reviewer</p>
                        <p class="text-xs text-gray-500">2024-01-15 10:00:00 by Jane Doe</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-2 h-2 mt-2 rounded-full bg-gray-300"></div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">Investigation started</p>
                        <p class="text-xs text-gray-500">2024-01-15 11:30:00 by John Smith</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Evidence -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Attached Evidence</h3>
            <ul class="space-y-2">
                <li class="flex items-center gap-2">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">transaction_history_2024.pdf</a>
                    <span class="text-xs text-gray-500"> (245 KB)</span>
                </li>
                <li class="flex items-center gap-2">
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-800">customer_kyc_verification.pdf</a>
                    <span class="text-xs text-gray-500"> (128 KB)</span>
                </li>
            </ul>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Update Status
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Add Note
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Attach Evidence
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Close Case
                </button>
            </div>
        </div>
    </div>
</body>
</html>