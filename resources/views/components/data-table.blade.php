@props(['data' => null, 'columns' => [], 'hasData' => false, 'columnCount' => 1])

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 border border-[#e5e5e5] dark:border-gray-700 rounded-xl overflow-hidden']) }}>
    @if($searchable ?? true)
        <div class="p-4 border-b border-[#e5e5e5] dark:border-gray-700">
            <x-input name="search" placeholder="Search..." inline />
        </div>
    @endif

    <table class="min-w-full divide-y divide-[#e5e5e5] dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
                @foreach($columns as $column)
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        @if(($sortable ?? true) && isset($column['sortable']) && $column['sortable'])
                            <a href="#" class="hover:text-gray-700 dark:hover:text-gray-200">
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
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-[#e5e5e5] dark:divide-gray-700">
            @if($hasData)
                {{ $slot }}
            @else
                <x-empty-state :message="($emptyMessage ?? 'No records found')" :colspan="$columnCount" />
            @endif
        </tbody>
    </table>

    @if($data instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
        <div class="p-4 border-t border-[#e5e5e5] dark:border-gray-700">
            {{ $data->links() }}
        </div>
    @endif
</div>