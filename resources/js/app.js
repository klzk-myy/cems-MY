// CEMS-MY Application Entry Point
import './bootstrap';
import Alpine from 'alpinejs';

// Make Alpine available globally
window.Alpine = Alpine;

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
