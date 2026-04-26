<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="w-60 bg-white border-r border-[#e5e5e5] flex flex-col shrink-0">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h1 class="text-lg font-semibold text-[#171717]">CEMS-MY</h1>
            </div>
            <nav class="flex-1 p-4 space-y-6 overflow-y-auto">
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Main</div>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 bg-[#f7f7f8] text-[#171717]">Dashboard</a>
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
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Users</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Rates</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Accounting</a>
                </div>
            </nav>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            {{-- Page Header --}}
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-[#171717]">Dashboard</h1>
                <p class="text-sm text-[#6b6b6b] mt-1">Welcome back. Here's what's happening today.</p>
            </div>

            {{-- Stats Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-sm text-[#6b6b6b] mb-1">Today's Volume</div>
                    <div class="text-2xl font-semibold text-[#171717]">RM 125,430</div>
                    <div class="text-xs text-[#22c55e] mt-1">↑ 12% from yesterday</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-sm text-[#6b6b6b] mb-1">Transactions</div>
                    <div class="text-2xl font-semibold text-[#171717]">47</div>
                    <div class="text-xs text-[#6b6b6b] mt-1">Pending: 3</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-sm text-[#6b6b6b] mb-1">Active Counters</div>
                    <div class="text-2xl font-semibold text-[#171717]">5/6</div>
                    <div class="text-xs text-[#6b6b6b] mt-1">1 counter closed</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-sm text-[#6b6b6b] mb-1">Alerts</div>
                    <div class="text-2xl font-semibold text-[#d4a843]">2</div>
                    <div class="text-xs text-[#6b6b6b] mt-1">Require attention</div>
                </div>
            </div>

            {{-- Recent Transactions --}}
            <div class="bg-white border border-[#e5e5e5] rounded-xl">
                <div class="px-6 py-4 border-b border-[#e5e5e5] flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-[#171717]">Recent Transactions</h2>
                    <a href="#" class="text-sm text-[#d4a843] hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                                <td class="px-4 py-3 font-mono text-xs text-[#171717]">TXN-20260426-001</td>
                                <td class="px-4 py-3 text-[#171717]">Ahmad Razali</td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">BUY</span></td>
                                <td class="px-4 py-3 text-[#171717] font-semibold">RM 5,000</td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span></td>
                                <td class="px-4 py-3 text-[#6b6b6b] text-xs">10:30 AM</td>
                            </tr>
                            <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                                <td class="px-4 py-3 font-mono text-xs text-[#171717]">TXN-20260426-002</td>
                                <td class="px-4 py-3 text-[#171717]">Siti Nurhaliza</td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-orange-100 text-orange-700">SELL</span></td>
                                <td class="px-4 py-3 text-[#171717] font-semibold">RM 12,500</td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span></td>
                                <td class="px-4 py-3 text-[#6b6b6b] text-xs">11:15 AM</td>
                            </tr>
                            <tr class="hover:bg-[#f7f7f8]/50">
                                <td class="px-4 py-3 font-mono text-xs text-[#171717]">TXN-20260426-003</td>
                                <td class="px-4 py-3 text-[#171717]">Lee Chong Wei</td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">BUY</span></td>
                                <td class="px-4 py-3 text-[#171717] font-semibold">RM 8,200</td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span></td>
                                <td class="px-4 py-3 text-[#6b6b6b] text-xs">11:45 AM</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>