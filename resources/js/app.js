// CEMS-MY Application Entry Point
import './bootstrap';
import Alpine from '@alpinejs/csp';
import { registerComponents } from './components';
import { ISO_CURRENCIES } from './iso-currencies';

// CSP-safe Alpine build: all x-data objects are registered by name, and
// inline event handlers are replaced by delegated listeners below so the
// Content-Security-Policy can drop 'unsafe-inline' and 'unsafe-eval'.
registerComponents(Alpine);

// Make Alpine available globally
window.Alpine = Alpine;

// Delegated replacements for inline event handlers:
//   data-print                -> window.print()
//   data-autosubmit           -> submit the enclosing form on change
//   data-confirm="message"    -> confirm() before a form submits
//   data-check-all="selector" -> toggle all matching checkboxes
//   data-add-currency-row     -> clone the setup-wizard custom-currency row
//   data-remove-currency-row  -> remove the enclosing custom-currency row
//   data-currency-code        -> auto-fill name + symbol from the ISO 4217 map
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-print]');
    if (trigger) {
        window.print();
    }

    const addRow = event.target.closest('[data-add-currency-row]');
    if (addRow) {
        const scope = addRow.closest('[data-currency-rows]');
        const list = scope?.querySelector('[data-currency-rows-list]');
        const template = scope?.querySelector('template[data-currency-row-template]');
        if (list && template) {
            const index = parseInt(list.dataset.nextIndex || list.children.length, 10);
            list.dataset.nextIndex = index + 1;
            const holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', index);
            const row = holder.firstElementChild;
            list.appendChild(row);
            row.querySelector('[data-currency-code]')?.focus();
        }
    }

    const removeRow = event.target.closest('[data-remove-currency-row]');
    if (removeRow) {
        removeRow.closest('[data-currency-row]')?.remove();
    }
});

document.addEventListener('change', (event) => {
    const target = event.target;

    if (target.dataset?.autosubmit !== undefined) {
        target.form?.submit();
    }

    const checkAll = target.dataset?.checkAll;
    if (checkAll) {
        document.querySelectorAll(checkAll).forEach((box) => {
            box.checked = target.checked;
        });
    }
});

document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
}, true);

document.addEventListener('input', (event) => {
    // Typing directly into a name/symbol field marks it user-owned so a later
    // code change does not overwrite it.
    const manual = event.target.closest?.('[data-currency-name], [data-currency-symbol]');
    if (manual) {
        delete manual.dataset.autofilled;
    }

    const codeInput = event.target.closest?.('[data-currency-code]');
    if (!codeInput) {
        return;
    }

    codeInput.value = codeInput.value.toUpperCase();

    const match = ISO_CURRENCIES[codeInput.value.trim()];
    const row = codeInput.closest('[data-currency-row]');
    if (!match || !row) {
        return;
    }

    // Fill empty fields and refresh values a previous autofill wrote, but
    // never overwrite text the user typed manually.
    const name = row.querySelector('[data-currency-name]');
    const symbol = row.querySelector('[data-currency-symbol]');
    if (name && (!name.value || name.dataset.autofilled)) {
        name.value = match[0];
        name.dataset.autofilled = '1';
    }
    if (symbol && (!symbol.value || symbol.dataset.autofilled)) {
        symbol.value = match[1];
        symbol.dataset.autofilled = '1';
    }
});

// Initialize Dark Mode
function initDarkMode() {
    const isDark = localStorage.getItem('darkMode') === 'true' ||
                  (!('darkMode' in localStorage) &&
                   window.matchMedia('(prefers-color-scheme: dark)').matches);

    if (isDark) {
        document.documentElement.classList.add('dark');
    }

    // Toggle button handler
    document.querySelectorAll('[data-toggle="dark-mode"]').forEach(button => {
        button.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode',
                document.documentElement.classList.contains('dark'));
        });
    });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDarkMode);
} else {
    initDarkMode();
}

// Start Alpine
Alpine.start();
