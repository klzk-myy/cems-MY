<x-layouts.app>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Journal Entries</h1>
                <p class="mt-1 text-sm text-gray-500">Manage double-entry journal entries</p>
            </div>
            <a href="{{ route('accounting.journal.create') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                + New Entry
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-4">
            <div class="flex flex-wrap gap-4">
                <input type="text" placeholder="Search entries..." class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg md:w-64">
                <select class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="posted">Posted</option>
                </select>
                <input type="date" class="px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">Filter</button>
            </div>
        </div>

        <!-- Journal Entries Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entry No.</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    @forelse($entries ?? [] as $entry)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">{{ $entry['date'] ?? '2026-05-01' }}</td>
                        <td class="px-4 py-3 text-sm font-mono">{{ $entry['entry_no'] ?? 'JE-0001' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $entry['description'] ?? 'Currency revaluation gain' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $entry['account'] ?? '7100-001' }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ $entry['debit'] ?? '0.00' }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ $entry['credit'] ?? '0.00' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Posted</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('accounting.journal.show', $entry['id'] ?? 1) }}" class="text-blue-600 hover:text-blue-800">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">No journal entries found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Showing 1-10 of 0 entries</p>
            <div class="flex gap-2">
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] disabled:opacity-50" disabled>Previous</button>
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] disabled:opacity-50" disabled>Next</button>
            </div>
        </div>
    </div>
</x-layouts.app>
