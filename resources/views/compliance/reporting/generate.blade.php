<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Generate Report</h1>
            <p class="mt-1 text-sm text-gray-500">Create a new compliance report</p>
        </div>

        <!-- Report Type Selection -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Select Report Type</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center p-4 border border-[#e5e5e5] rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="report_type" value="str" class="w-4 h-4 text-blue-600">
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">STR</p>
                        <p class="text-xs text-gray-500">Suspicious Transaction Report</p>
                    </div>
                </label>
                <label class="flex items-center p-4 border border-[#e5e5e5] rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="report_type" value="edd" class="w-4 h-4 text-blue-600">
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">EDD</p>
                        <p class="text-xs text-gray-500">Enhanced Due Diligence Report</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Report Parameters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Parameters</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Branch</label>
                    <select class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                        <option value="">All Branches</option>
                        <option value="kl">Kuala Lumpur</option>
                        <option value="penang">Penang</option>
                        <option value="johor">Johor</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Start Date</label>
                    <input type="date" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">End Date</label>
                    <input type="date" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Currency</label>
                    <select class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                        <option value="">All Currencies</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="SGD">SGD</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Report Options -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Report Options</h3>
            <div class="space-y-3">
                <label class="flex items-center gap-3">
                    <input type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-900">Include transaction details</span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-900">Include customer information</span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-900">Generate summary statistics</span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm text-gray-900">Export in BNM specified format</span>
                </label>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3">
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                Cancel
            </button>
            <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                Generate Report
            </button>
        </div>
    </div>
</body>
</html>