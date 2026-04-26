<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchange Rates - CEMS-MY</title>
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
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Users</a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 bg-[#f7f7f8] text-[#171717]">Rates</a>
                </div>
            </nav>
        </aside>
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-[#171717]">Exchange Rates</h1>
                    <p class="text-sm text-[#6b6b6b] mt-1">Daily rate management and market rates</p>
                </div>
                <div class="flex gap-3">
                    <a href="#" class="px-4 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] rounded-lg hover:bg-[#f7f7f8]">Fetch Rates</a>
                    <a href="#" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Override Rate</a>
                </div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Currency</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Buy Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Sell Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Spread</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">USD</span>
                                    <span class="text-[#171717] font-medium">USD</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-[#171717]">4.7200</td>
                            <td class="px-4 py-3 text-[#171717]">4.7500</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">0.63%</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">26 Apr 09:00</td>
                        </tr>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">EUR</span>
                                    <span class="text-[#171717] font-medium">EUR</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-[#171717]">5.1800</td>
                            <td class="px-4 py-3 text-[#171717]">5.2200</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">0.77%</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">26 Apr 09:00</td>
                        </tr>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">GBP</span>
                                    <span class="text-[#171717] font-medium">GBP</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-[#171717]">6.0500</td>
                            <td class="px-4 py-3 text-[#171717]">6.1000</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">0.83%</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">26 Apr 09:00</td>
                        </tr>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">SGD</span>
                                    <span class="text-[#171717] font-medium">SGD</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-[#171717]">3.5200</td>
                            <td class="px-4 py-3 text-[#171717]">3.5400</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">0.57%</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">26 Apr 09:00</td>
                        </tr>
                        <tr class="hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">JPY</span>
                                    <span class="text-[#171717] font-medium">JPY</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-[#171717]">0.0320</td>
                            <td class="px-4 py-3 text-[#171717]">0.0325</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">1.56%</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">26 Apr 09:00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
