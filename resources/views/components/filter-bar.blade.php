@props(['method' => 'GET', 'class' => null])

<div {{ $attributes->merge(['class' => "bg-white dark:bg-gray-800 border border-[#e5e5e5] dark:border-gray-700 rounded-xl p-4 mb-6" . ($class ? " $class" : "")]) }}>
    <form method="{{ $method }}" class="flex flex-wrap gap-4 items-end">
        {{ $slot }}
    </form>
</div>