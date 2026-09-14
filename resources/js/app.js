// CEMS-MY Application Entry Point
import './bootstrap';
import Alpine from '@alpinejs/csp';
import { registerComponents } from './components';

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
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-print]');
    if (trigger) {
        window.print();
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
