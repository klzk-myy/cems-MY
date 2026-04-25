{{-- Modal Component --}}
{{-- Trigger via: $dispatch('open-modal', 'modal-name') --}}
@props(['name', 'title' => ''])

<div
    x-data="{ open: false }"
    @open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    @close-modal.window="if ($event.detail === '{{ $name }}') open = false"
>
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-[--color-ink]/50" @click="open = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                @if($title)
                <h3 class="text-lg font-semibold text-[--color-ink] mb-4">{{ $title }}</h3>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</div>