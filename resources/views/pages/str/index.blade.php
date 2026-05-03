<x-app-layout title="STR Reports">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">STR Reports</h1>
            <a href="{{ route('str.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                New STR
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Draft</div>
                <div class="text-2xl font-bold">{{ $stats['draft'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Pending Review</div>
                <div class="text-2xl font-bold">{{ $stats['pending_review'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Pending Approval</div>
                <div class="text-2xl font-bold">{{ $stats['pending_approval'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Submitted</div>
                <div class="text-2xl font-bold">{{ $stats['submitted'] ?? 0 }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Acknowledged</div>
                <div class="text-2xl font-bold">{{ $stats['acknowledged'] ?? 0 }}</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Filing Deadline</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($strReports ?? [] as $str)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-sm">{{ $str->reference }}</td>
                        <td class="px-4 py-3">{{ $str->customer->full_name ?? 'N/A' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs 
                                @if($str->status === 'Draft') bg-gray-100 text-gray-800
                                @elseif($str->status === 'Pending_Review') bg-yellow-100 text-yellow-800
                                @elseif($str->status === 'Pending_Approval') bg-blue-100 text-blue-800
                                @elseif($str->status === 'Submitted') bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ str_replace('_', ' ', $str->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $str->filing_deadline?->format('M d, Y') ?? 'N/A' }}</td>
                        <td class="px-4 py-3">{{ $str->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('str.show', $str) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No STR reports found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $strReports->withQueryString()->links() ?? '' }}
        </div>
    </div>
</x-app-layout>