@props(['data' => null, 'columns' => [], 'hasData' => false, 'columnCount' => 1])

<div {{ $attributes->merge(['class' => 'bg-surface border border-border rounded-xl overflow-hidden']) }}>
    @if($searchable ?? true)
        <div class="p-4 border-b border-border">
            <x-input name="search" placeholder="Search..." inline />
        </div>
    @endif

    <table class="min-w-full divide-y divide-border">
        <thead class="bg-canvas-subtle">
            <tr>
                @foreach($columns as $column)
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                        @if(($sortable ?? true) && isset($column['sortable']) && $column['sortable'])
                            <a href="#" class="hover:text-ink">
                                {{ $column['label'] }}
                            </a>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-surface divide-y divide-border">
            @if($hasData)
                {{ $slot }}
            @else
                <x-empty-state :message="($emptyMessage ?? 'No records found')" :colspan="$columnCount" />
            @endif
        </tbody>
    </table>

    @if($data instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
        <div class="p-4 border-t border-border">
            {{ $data->links() }}
        </div>
    @endif
</div>