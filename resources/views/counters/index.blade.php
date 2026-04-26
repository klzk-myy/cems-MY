<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counters - CEMS-MY</title>
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
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 bg-[#f7f7f8] text-[#171717]">Counters</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Management</div>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Customers</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Compliance</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Reports</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">System</div>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Users</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Rates</a>
                </div>
            </nav>
        </aside>
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-[#171717]">Counters</h1>
                    <p class="text-sm text-[#6b6b6b] mt-1">Manage teller counters and till sessions</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Open Counter</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-[#171717]">Counter 1</h3>
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#6b6b6b]">Teller</span>
                            <span class="text-[#171717]">Ahmad Razali</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6b6b6b]">MYR Balance</span>
                            <span class="text-[#171717] font-semibold">RM 50,000</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-[#171717]">Counter 2</h3>
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#6b6b6b]">Teller</span>
                            <span class="text-[#171717]">Siti Nurhaliza</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6b6b6b]">MYR Balance</span>
                            <span class="text-[#171717] font-semibold">RM 45,000</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-[#171717]">Counter 3</h3>
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-[#f7f7f8] text-[#6b6b6b]">Closed</span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#6b6b6b]">Teller</span>
                            <span class="text-[#171717]">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#6b6b6b]">MYR Balance</span>
                            <span class="text-[#171717] font-semibold">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Counter</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Current Teller</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">MYR Float</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">Counter 1</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span></td>
                            <td class="px-4 py-3 text-[#171717]">Ahmad Razali</td>
                            <td class="px-4 py-3 text-[#171717] font-semibold">RM 50,000</td>
                            <td class="px-4 py-3"><a href="#" class="text-[#d4a843] hover:underline">View</a></td>
                        </tr>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">Counter 2</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span></td>
                            <td class="px-4 py-3 text-[#171717]">Siti Nurhaliza</td>
                            <td class="px-4 py-3 text-[#171717] font-semibold">RM 45,000</td>
                            <td class="px-4 py-3"><a href="#" class="text-[#d4a843] hover:underline">View</a></td>
                        </tr>
                        <tr class="hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">Counter 3</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-[#f7f7f8] text-[#6b6b6b]">Closed</span></td>
                            <td class="px-4 py-3 text-[#6b6b6b]">-</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">-</td>
                            <td class="px-4 py-3"><a href="#" class="text-[#d4a843] hover:underline">Open</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
