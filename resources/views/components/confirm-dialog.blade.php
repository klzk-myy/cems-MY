{{--
    Shared confirmation dialog. Forms carry data-confirm="message"; the
    delegated submit handler in app.js opens this instead of window.confirm.
    Kept as static markup — app.js toggles [hidden] and wires the buttons.
--}}
<div id="confirm-dialog" hidden
     class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title"
     aria-describedby="confirm-dialog-message">
    <div class="absolute inset-0 bg-black/50" data-confirm-cancel aria-hidden="true"></div>

    <div class="relative w-full max-w-sm rounded-xl border border-border bg-surface p-6 shadow-xl">
        <h2 id="confirm-dialog-title" class="text-base font-semibold text-ink">Please confirm</h2>
        <p id="confirm-dialog-message" data-confirm-message class="mt-2 text-sm text-ink-muted"></p>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-confirm-cancel
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-ink transition-colors hover:bg-canvas-subtle focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                Cancel
            </button>
            <button type="button" data-confirm-ok
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-danger px-4 py-2 text-sm font-medium text-on-danger transition-colors hover:bg-danger-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                Confirm
            </button>
        </div>
    </div>
</div>
