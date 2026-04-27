<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Branch - CEMS-MY</title>
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
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-[#171717]">Create Branch</h1>
                <p class="text-sm text-[#6b6b6b] mt-1">Add a new branch location</p>
            </div>
            <form method="POST" action="{{ route('branches.store') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-[#171717] mb-2">Branch Code</label>
                        <input type="text" name="code" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#171717] mb-2">Branch Name</label>
                        <input type="text" name="name" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#171717] mb-2">Type</label>
                        <select name="type" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                            @foreach($branchTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#171717] mb-2">Parent Branch</label>
                        <select name="parent_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                            <option value="">None</option>
                            @foreach($parentBranches ?? [] as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-[#171717] mb-2">Address</label>
                        <input type="text" name="address" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#171717] mb-2">City</label>
                        <input type="text" name="city" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#171717] mb-2">State</label>
                        <input type="text" name="state" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#171717] mb-2">Phone</label>
                        <input type="text" name="phone" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#171717] mb-2">Email</label>
                        <input type="email" name="email" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div class="md:col-span-2 flex items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded border-[#e5e5e5]">
                            <span class="text-sm text-[#171717]">Active</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_main" value="1" class="w-4 h-4 rounded border-[#e5e5e5]">
                            <span class="text-sm text-[#171717]">Main Branch</span>
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Create Branch</button>
                    <a href="{{ route('branches.index') }}" class="px-4 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] rounded-lg hover:bg-[#f7f7f8]">Cancel</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>