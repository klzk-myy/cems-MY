<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Schedule</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Report Schedule</h1>
                    <p class="mt-1 text-sm text-gray-500">Configure automatic report generation</p>
                </div>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Add Schedule
                </button>
            </div>
        </div>

        <!-- Schedule Cards -->
        <div class="space-y-4">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 flex items-center justify-center bg-blue-100 rounded-lg">
                            <span class="text-lg font-bold text-blue-700">CTOS</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">CTOS Report Generation</p>
                            <p class="text-xs text-gray-500">15th of every month at 00:00</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
                        <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                            Edit
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 flex items-center justify-center bg-green-100 rounded-lg">
                            <span class="text-lg font-bold text-green-700">LMCA</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">LMCA Report Generation</p>
                            <p class="text-xs text-gray-500">1st of every month at 00:00</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
                        <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                            Edit
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 flex items-center justify-center bg-red-100 rounded-lg">
                            <span class="text-lg font-bold text-red-700">QLVR</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">QLVR Report Generation</p>
                            <p class="text-xs text-gray-500">15th of first month every quarter at 00:00</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Paused</span>
                        <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                            Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>