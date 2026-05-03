<x-app-layout title="Compliance Dashboard">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Compliance Dashboard</h1>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Open Flags</div>
                <div class="text-2xl font-bold text-yellow-600">{{ $stats['open'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Under Review</div>
                <div class="text-2xl font-bold text-blue-600">{{ $stats['under_review'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Resolved Today</div>
                <div class="text-2xl font-bold text-green-600">{{ $stats['resolved_today'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">High Priority</div>
                <div class="text-2xl font-bold text-red-600">{{ $stats['high_priority'] ?? 0 }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">STR Draft</div>
                <div class="text-xl font-bold">{{ $strStats['draft'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">STR Pending Review</div>
                <div class="text-xl font-bold">{{ $strStats['pending_review'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">STR Submitted</div>
                <div class="text-xl font-bold">{{ $strStats['submitted'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">STR Overdue</div>
                <div class="text-xl font-bold text-red-600">{{ $strStats['overdue'] ?? 0 }}</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b">
                <h2 class="text-lg font-semibold">Flagged Transactions</h2>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-2">Transaction</th>
                        <th class="px-4 py-2">Flag Type</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Created</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($flags ?? [] as $flag)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-sm">{{ $flag->transaction_id }}</td>
                        <td class="px-4 py-2">{{ $flag->flag_type }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs 
                                @if($flag->status === 'Open') bg-yellow-100 text-yellow-800
                                @elseif($flag->status === 'Under_Review') bg-blue-100 text-blue-800
                                @else bg-green-100 text-green-800 @endif">
                                {{ $flag->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2">{{ $flag->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-2">
                            <form method="POST" action="{{ route('compliance.flags.assign', $flag) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-blue-600 hover:underline">Assign to Me</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No flagged transactions.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $flags->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>