<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <aside class="w-60 bg-white border-r border-[#e5e5e5] flex flex-col shrink-0">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h1 class="text-lg font-semibold text-[#171717]">CEMS-MY</h1>
            </div>
            <nav class="flex-1 p-4 space-y-6 overflow-y-auto">
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Main</div>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Dashboard</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Transactions</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Counters</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Management</div>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Customers</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Compliance</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Reports</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">System</div>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 bg-[#f7f7f8] text-[#171717]">Users</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Rates</a>
                </div>
            </nav>
        </aside>
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-[#171717]">Users</h1>
                    <p class="text-sm text-[#6b6b6b] mt-1">Manage system users and roles</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Add User</a>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Role</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Branch</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">Ahmad Razali</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">ahmad@cems.my</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Teller</span></td>
                            <td class="px-4 py-3 text-[#6b6b6b]">Kuala Lumpur</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span></td>
                        </tr>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">Siti Nurhaliza</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">siti@cems.my</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">Teller</span></td>
                            <td class="px-4 py-3 text-[#6b6b6b]">Kuala Lumpur</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span></td>
                        </tr>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">Mohd Faizal</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">faizal@cems.my</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-purple-100 text-purple-700">Manager</span></td>
                            <td class="px-4 py-3 text-[#6b6b6b]">Kuala Lumpur</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span></td>
                        </tr>
                        <tr class="hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">Nurul Huda</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">nurul@cems.my</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-orange-100 text-orange-700">Compliance</span></td>
                            <td class="px-4 py-3 text-[#6b6b6b]">Kuala Lumpur</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
