@props(['method' => 'GET', 'class' => null])

<div class="bg-white border border-[#e5e5e5] rounded-xl p-4 mb-6 {{ $class }}">
    <form method="{{ $method }}" class="flex flex-wrap gap-4 items-end">
        {{ $slot }}
    </form>
</div>