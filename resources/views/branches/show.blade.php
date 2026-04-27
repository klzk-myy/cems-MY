<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $branch->name }} - CEMS-MY</title>
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
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Dashboard</a>
                    <a href="{{ route('transactions.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Transactions</a>
                    <a href="{{ route('counters.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Counters</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Management</div>
                    <a href="{{ route('customers.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Customers</a>
                    <a href="{{ route('compliance') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Compliance</a>
                    <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Reports</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">System</div>
                    <a href="{{ route('users.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Users</a>
                    <a href="{{ route('rates.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Rates</a>
                    <a href="{{ route('accounting.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Accounting</a>
                </div>
            </nav>
        </aside>
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-[#171717]">{{ $branch->code }} - {{ $branch->name }}</h1>
                    <p class="text-sm text-[#6b6b6b] mt-1">Branch details and statistics</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('branches.edit', $branch) }}" class="px-4 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] rounded-lg hover:bg-[#f7f7f8]">Edit</a>
                    <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#ef4444] rounded-lg hover:bg-[#dc2626]" onclick="return confirm('Deactivate this branch?')">Deactivate</button>
                    </form>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-sm text-[#6b6b6b] mb-1">Users</div>
                    <div class="text-2xl font-semibold text-[#171717]">{{ number_format($stats['user_count'] ?? 0) }}</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-sm text-[#6b6b6b] mb-1">Counters</div>
                    <div class="text-2xl font-semibold text-[#171717]">{{ number_format($stats['counter_count'] ?? 0) }}</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-sm text-[#6b6b6b] mb-1">Today's Transactions</div>
                    <div class="text-2xl font-semibold text-[#171717]">{{ number_format($stats['transaction_today'] ?? 0) }}</div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="text-sm text-[#6b6b6b] mb-1">Monthly Transactions</div>
                    <div class="text-2xl font-semibold text-[#171717]">{{ number_format($stats['transaction_month'] ?? 0) }}</div>
                </div>
            </div>
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h2 class="text-lg font-semibold text-[#171717] mb-4">Branch Information</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-[#6b6b6b]">Type</dt><dd class="text-[#171717] font-medium">{{ ucfirst(str_replace('_', ' ', $branch->type)) }}</dd></div>
                    <div><dt class="text-[#6b6b6b]">Status</dt><dd class="text-[#171717] font-medium">{{ $branch->is_active ? 'Active' : 'Inactive' }}</dd></div>
                    <div><dt class="text-[#6b6b6b]">Address</dt><dd class="text-[#171717] font-medium">{{ $branch->address ?? 'N/A' }}</dd></div>
                    <div><dt class="text-[#6b6b6b]">Phone</dt><dd class="text-[#171717] font-medium">{{ $branch->phone ?? 'N/A' }}</dd></div>
                    <div><dt class="text-[#6b6b6b]">Email</dt><dd class="text-[#171717] font-medium">{{ $branch->email ?? 'N/A' }}</dd></div>
                    @if($branch->parent_id)
                    <div><dt class="text-[#6b6b6b]">Parent Branch</dt><dd class="text-[#171717] font-medium">{{ $branch->parent->code ?? 'N/A' }}</dd></div>
                    @endif
                </dl>
            </div>
        </main>
    </div>
</body>
</html>