<@props([
    'title' => '',
    'description' => null,
    'actions' => null
])

<div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-[#e5e5e5] flex items-start justify-between gap-4">
        <div>
            @if($title)
                <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
            @endif
            @if($description)
                <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>
        @if($actions)
            <div class="flex items-center gap-2 flex-shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>
    <div>
        {{ $slot }}
    </div>
</div>