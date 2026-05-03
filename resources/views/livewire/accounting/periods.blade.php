<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Accounting Periods</h1>
        <p class="text-sm text-gray-500">Manage accounting period opening and closing</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Periods</h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Period Code</th>
                        <th>Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($periods as $period)
                    <tr>
                        <td class="font-mono">{{ $period['period_code'] }}</td>
                        <td>{{ $period['name'] }}</td>
                        <td>{{ $period['start_date'] }}</td>
                        <td>{{ $period['end_date'] }}</td>
                        <td class="text-gray-500">{{ ucfirst($period['period_type']) }}</td>
                        <td>
                            @if($period['is_closed'])
                                <span class="badge badge-default">Closed</span>
                            @else
                                <span class="badge badge-success">Open</span>
                            @endif
                        </td>
                        <td>
                            @if(!$period['is_closed'])
                                <button
                                    wire:click="closePeriod({{ $period['id'] }})"
                                    wire:confirm="Are you sure you want to close this period? This action cannot be undone."
                                    class="btn btn-ghost btn-sm text-red-600">
                                    Close
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">No periods found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>