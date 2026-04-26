<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - CEMS-MY</title>
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
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 bg-[#f7f7f8] text-[#171717]">Reports</a>
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
                    <h1 class="text-2xl font-semibold text-[#171717]">Reports</h1>
                    <p class="text-sm text-[#6b6b6b] mt-1">BNM compliance and regulatory reports</p>
                </div>
                <a href="#" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Generate Report</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center bg-blue-100 rounded-lg">
                            <span class="text-blue-600 text-lg">📊</span>
                        </div>
                        <h3 class="text-sm font-semibold text-[#171717]">MSB2 Report</h3>
                    </div>
                    <p class="text-xs text-[#6b6b6b] mb-4">Daily transaction summary for Bank Negara Malaysia</p>
                    <a href="#" class="text-sm text-[#d4a843] hover:underline">View Report</a>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center bg-green-100 rounded-lg">
                            <span class="text-green-600 text-lg">💵</span>
                        </div>
                        <h3 class="text-sm font-semibold text-[#171717]">LCTR</h3>
                    </div>
                    <p class="text-xs text-[#6b6b6b] mb-4">Large Cash Transaction Report (≥ RM 25,000)</p>
                    <a href="#" class="text-sm text-[#d4a843] hover:underline">View Report</a>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center bg-orange-100 rounded-lg">
                            <span class="text-orange-600 text-lg">🚨</span>
                        </div>
                        <h3 class="text-sm font-semibold text-[#171717]">STR</h3>
                    </div>
                    <p class="text-xs text-[#6b6b6b] mb-4">Suspicious Transaction Report for AML/CFT compliance</p>
                    <a href="#" class="text-sm text-[#d4a843] hover:underline">View Reports</a>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center bg-purple-100 rounded-lg">
                            <span class="text-purple-600 text-lg">📈</span>
                        </div>
                        <h3 class="text-sm font-semibold text-[#171717]">CTR</h3>
                    </div>
                    <p class="text-xs text-[#6b6b6b] mb-4">Cash Transaction Report for transactions ≥ RM 25,000</p>
                    <a href="#" class="text-sm text-[#d4a843] hover:underline">View Report</a>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center bg-yellow-100 rounded-lg">
                            <span class="text-yellow-600 text-lg">📋</span>
                        </div>
                        <h3 class="text-sm font-semibold text-[#171717]">LMCA</h3>
                    </div>
                    <p class="text-xs text-[#6b6b6b] mb-4">Monthly Large Cash Transaction Summary</p>
                    <a href="#" class="text-sm text-[#d4a843] hover:underline">View Report</a>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center bg-red-100 rounded-lg">
                            <span class="text-red-600 text-lg">⚠️</span>
                        </div>
                        <h3 class="text-sm font-semibold text-[#171717]">EDD Reports</h3>
                    </div>
                    <p class="text-xs text-[#6b6b6b] mb-4">Enhanced Due Diligence reports for high-risk customers</p>
                    <a href="#" class="text-sm text-[#d4a843] hover:underline">View Reports</a>
                </div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Report</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Period</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Generated</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">MSB2 Daily Summary</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">25 Apr 2026</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">25 Apr 2026 18:00</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Submitted</span></td>
                        </tr>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">LCTR - April 2026</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">Apr 2026</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">26 Apr 2026 09:00</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span></td>
                        </tr>
                        <tr class="hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 text-[#171717] font-medium">STR-2026-0012</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">23 Apr 2026</td>
                            <td class="px-4 py-3 text-[#6b6b6b]">23 Apr 2026 14:30</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Submitted</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>