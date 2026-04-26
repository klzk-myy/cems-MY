<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance - CEMS-MY</title>
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
                    <a href="#" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 bg-[#f7f7f8] text-[#171717]">Compliance</a>
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
                    <h1 class="text-2xl font-semibold text-[#171717]">Compliance</h1>
                    <p class="text-sm text-[#6b6b6b] mt-1">AML/CFT monitoring and regulatory reporting</p>
                </div>
                <div class="flex gap-3">
                    <a href="#" class="px-4 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] rounded-lg hover:bg-[#f7f7f8]">View Alerts</a>
                    <a href="#" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">New Case</a>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-2xl font-semibold text-[#171717]">12</div>
                    <div class="text-sm text-[#6b6b6b] mt-1">Open Alerts</div>
                    <div class="text-xs text-[#ef4444] mt-2">3 Critical</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-2xl font-semibold text-[#171717]">5</div>
                    <div class="text-sm text-[#6b6b6b] mt-1">Pending Cases</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-2xl font-semibold text-[#171717]">3</div>
                    <div class="text-sm text-[#6b6b6b] mt-1">STR Submitted</div>
                    <div class="text-xs text-[#6b6b6b] mt-2">This month</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-2xl font-semibold text-[#171717]">8</div>
                    <div class="text-sm text-[#6b6b6b] mt-1">CTOS Filed</div>
                    <div class="text-xs text-[#6b6b6b] mt-2">This month</div>
                </div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Alert ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Severity</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 font-mono text-xs text-[#171717]">ALR-20260426-001</td>
                            <td class="px-4 py-3 text-[#171717]">Velocity Alert</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Critical</span></td>
                            <td class="px-4 py-3 text-[#171717]">John Tan</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Under Review</span></td>
                        </tr>
                        <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 font-mono text-xs text-[#171717]">ALR-20260426-002</td>
                            <td class="px-4 py-3 text-[#171717]">Structuring Detected</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-orange-100 text-orange-700">High</span></td>
                            <td class="px-4 py-3 text-[#171717]">Ahmad Razali</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Under Review</span></td>
                        </tr>
                        <tr class="hover:bg-[#f7f7f8]/50">
                            <td class="px-4 py-3 font-mono text-xs text-[#171717]">ALR-20260425-003</td>
                            <td class="px-4 py-3 text-[#171717]">Sanctions Rescreen</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Medium</span></td>
                            <td class="px-4 py-3 text-[#171717]">Siti Nurhaliza</td>
                            <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Resolved</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
