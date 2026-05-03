<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sanctions Entries</title>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sanctions Entries</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage sanctions list entries</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Add Entry
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <div class="flex flex-wrap gap-4">
                <input type="text" placeholder="Search by name or reference..." class="flex-1 px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Sources</option>
                    <option value="ofac">OFAC SDN</option>
                    <option value="un">UN Security Council</option>
                    <option value="eu">EU Sanctions List</option>
                    <option value="bnm">BNM List</option>
                </select>
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Types</option>
                    <option value="individual">Individual</option>
                    <option value="organization">Organization</option>
                    <option value="vessel">Vessel</option>
                    <option value="aircraft">Aircraft</option>
                </select>
                <select class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="deleted">Deleted</option>
                </select>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Search
                </button>
            </div>
        </div>

        <!-- Entries Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entry ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">ENTRY-2024-001</td>
                        <td class="px-4 py-3 text-sm text-gray-900">John Doe</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Individual</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">OFAC</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">OFAC-12345</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">ENTRY-2024-002</td>
                        <td class="px-4 py-3 text-sm text-gray-900">ABC Corporation</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Organization</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-700">UN</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">UN-67890</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">ENTRY-2024-003</td>
                        <td class="px-4 py-3 text-sm text-gray-900">Jane Smith</td>
                        <td class="px-4 py-3 text-sm text-gray-500">Individual</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">BNM</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">BNM-11111</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="#" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>