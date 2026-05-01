<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" style="display: none" id="loading-overlay">
    <div class="bg-white rounded-lg p-6 flex items-center gap-4">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[--color-accent]"></div>
        <span class="text-sm text-[--color-ink-muted]">Loading...</span>
    </div>
</div>

<script>
window.addEventListener('load', () => {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        setTimeout(() => { overlay.style.display = 'none'; }, 100);
    }
});
document.addEventListener('livewire:loading', () => {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) { overlay.style.display = 'flex'; }
});
document.addEventListener('livewire:loaded', () => {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) { overlay.style.display = 'none'; }
});
</script>
