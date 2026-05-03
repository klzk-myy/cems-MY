<x-layouts.app>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Journal Entry</h1>
                <p class="mt-1 text-sm text-gray-500">Entry #{{ $entry['entry_no'] ?? 'JE-0001' }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('accounting.journal.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]">
                    Back
                </a>
                @if(($entry['status'] ?? 'posted') === 'draft')
                <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">Edit</button>
                @endif
            </div>
        </div>

        <!-- Entry Details -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-sm text-gray-500">Date</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $entry['date'] ?? '2026-05-01' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Reference</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $entry['reference'] ?? 'JE-0001' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p class="mt-1">
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Posted</span>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created By</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $entry['created_by'] ?? 'Admin User' }}</p>
                </div>
            </div>
            <div class="mt-6">
                <p class="text-sm text-gray-500">Description</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $entry['description'] ?? 'Currency revaluation gain' }}</p>
            </div>
        </div>

        <!-- Journal Lines -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-[#e5e5e5]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e5e5]">
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono">1100-001</td>
                        <td class="px-4 py-3 text-sm">Cash MYR</td>
                        <td class="px-4 py-3 text-sm">Currency revaluation</td>
                        <td class="px-4 py-3 text-sm text-right">500.00</td>
                        <td class="px-4 py-3 text-sm text-right">0.00</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono">7100-001</td>
                        <td class="px-4 py-3 text-sm">Revaluation Gain</td>
                        <td class="px-4 py-3 text-sm">Currency revaluation</td>
                        <td class="px-4 py-3 text-sm text-right">0.00</td>
                        <td class="px-4 py-3 text-sm text-right">500.00</td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50 border-t border-[#e5e5e5]">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-sm font-medium text-gray-900">Total</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">500.00</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">500.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Audit Trail -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-4">Audit Trail</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-[#e5e5e5]">
                    <div>
                        <p class="text-sm text-gray-900">Created</p>
                        <p class="text-xs text-gray-500">Admin User</p>
                    </div>
                    <p class="text-sm text-gray-500">2026-05-01 09:00:00</p>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-[#e5e5e5]">
                    <div>
                        <p class="text-sm text-gray-900">Posted</p>
                        <p class="text-xs text-gray-500">Admin User</p>
                    </div>
                    <p class="text-sm text-gray-500">2026-05-01 09:05:00</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
