<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDD Template - Standard EDD</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">EDD Template Details</h1>
                    <p class="mt-1 text-sm text-gray-500">Standard EDD</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>

        <!-- Template Configuration -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Template Configuration</h3>
                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Active</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Template Name</label>
                    <p class="text-sm text-gray-900">Standard EDD</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Version</label>
                    <p class="text-sm text-gray-900">1.2</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Created</label>
                    <p class="text-sm text-gray-900">2023-12-01</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Last Updated</label>
                    <p class="text-sm text-gray-900">2024-01-10</p>
                </div>
            </div>
        </div>

        <!-- Required Fields -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Required Fields</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Field Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Required</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Validation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Source of Funds</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Text</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Yes</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">Min 50 characters</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Source of Wealth</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Text</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Yes</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">Min 50 characters</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Annual Income</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Number</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Yes</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">Positive number</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">Occupation</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Text</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Optional</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">-</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Additional Documents -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Required Documents</h3>
            <ul class="space-y-2">
                <li class="flex items-center gap-2 text-sm text-gray-900">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Proof of Address
                </li>
                <li class="flex items-center gap-2 text-sm text-gray-900">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Bank Statements (Last 6 Months)
                </li>
                <li class="flex items-center gap-2 text-sm text-gray-900">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Employment Verification Letter
                </li>
            </ul>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Edit Template
                </button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                    Duplicate
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