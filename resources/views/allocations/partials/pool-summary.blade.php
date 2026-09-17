{{-- Branch pool breakdown shared by the manager create form and the
     teller My Allocations page. Expects $poolSummary from
     AllocationController::poolSummary(). --}}
@forelse($poolSummary as $group)
    <div @if(! $loop->first) class="mt-4" @endif>
        @if(count($poolSummary) > 1)
            <h4 class="text-xs font-semibold text-ink-muted uppercase mb-2">{{ $group['branch'] }}</h4>
        @endif
        <x-table>
            <x-slot:thead>
                <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Pool Total</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Available to Allocate</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Allocated to Tellers</th>
            </x-slot:thead>
            <x-slot:tbody>
                @foreach($group['rows'] as $row)
                    <tr class="border-t border-border">
                        <td class="px-4 py-3 font-medium text-ink">{{ $row['currency'] }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['total'], 4) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row['available'], 4) }}</td>
                        <td class="px-4 py-3">
                            @forelse($row['tellers'] as $t)
                                <span class="inline-block mr-3">{{ $t['name'] }}: {{ number_format($t['amount'], 4) }}</span>
                            @empty
                                <span class="text-ink-muted">—</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </x-slot:tbody>
        </x-table>
    </div>
@empty
    <p class="text-sm text-ink-muted">No branch pool data.</p>
@endforelse
