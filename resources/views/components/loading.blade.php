<div class="loading-overlay" x-data="{ loading: $loading ?? false }" x-show="loading" x-transition.opacity>
    <div class="fixed inset-0 bg-[--color-ink]/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 shadow-xl flex items-center gap-4">
            <div class="w-8 h-8 border-4 border-[--color-accent] border-t-transparent rounded-full animate-spin"></div>
            <div>
                <p class="font-medium text-[--color-ink]">{{ $message ?? 'Processing...' }}</p>
                <p class="text-sm text-[--color-ink-muted]">Please wait</p>
            </div>
        </div>
    </div>
</div>
