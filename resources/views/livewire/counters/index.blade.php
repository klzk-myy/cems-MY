<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Counters</h1>
            <a href="{{ route('counters.open') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Open Counter</a>
        </div>

        <div class="grid grid-cols-3 gap-6 mb-6">
            @foreach($counters as $counter)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-medium text-[var(--color-ink)]">Counter #{{ $counter->number }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $counter->branch->name ?? 'Main' }}</p>
                <div class="mt-4">
                    @if($counter->status === 'open')
                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Open</span>
                        <p class="mt-2 text-sm">Opened: {{ $counter->opened_at->format('H:i') }}</p>
                    @else
                        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Closed</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Counter</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">User</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($counterSessions as $session)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">#{{ $session->counter->number }}</td>
                        <td class="px-4 py-3">{{ $session->user->name }}</td>
                        <td class="px-4 py-3">
                            @if($session->status === 'open')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Open</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">Closed</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('counters.show', $session->id) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-center">No active counter sessions</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>