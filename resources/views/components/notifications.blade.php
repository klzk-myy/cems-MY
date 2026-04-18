{{-- Success Toast --}}
<div class="toast toast-success" x-data="{ show: $show ?? false }" x-show="show" x-transition.opacity.duration.300ms>
    <div class="fixed top-4 right-4 bg-[--color-success] text-white px-6 py-4 rounded-xl shadow-lg flex items-center gap-3 z-50">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span>{{ $message ?? 'Success!' }}</span>
        <button @click="show = false" class="ml-4 hover:opacity-75">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Error Toast --}}
<div class="toast toast-error" x-data="{ show: $show ?? false }" x-show="show" x-transition.opacity.duration.300ms>
    <div class="fixed top-4 right-4 bg-[--color-danger] text-white px-6 py-4 rounded-xl shadow-lg flex items-center gap-3 z-50">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>{{ $message ?? 'Error occurred' }}</span>
        <button @click="show = false" class="ml-4 hover:opacity-75">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Warning Toast --}}
<div class="toast toast-warning" x-data="{ show: $show ?? false }" x-show="show" x-transition.opacity.duration.300ms>
    <div class="fixed top-4 right-4 bg-[--color-warning] text-white px-6 py-4 rounded-xl shadow-lg flex items-center gap-3 z-50">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <span>{{ $message ?? 'Warning' }}</span>
        <button @click="show = false" class="ml-4 hover:opacity-75">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>

{{-- Notification Scripts --}}
<script>
    // Auto-hide notifications after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const toasts = document.querySelectorAll('.toast');
        toasts.forEach(toast => {
            setTimeout(() => {
                toast.remove();
            }, 5000);
        });
    });
</script>
