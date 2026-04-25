<div class="app-shell">
    <x-sidebar />
    <div class="main-wrapper">
        <header class="sticky top-0 z-40 bg-[--content-bg]/80 backdrop-blur-md border-b border-[--color-border]">
            <div class="flex items-center justify-between px-8 py-4">
                {{-- Page Title --}}
                <div>
                    @hasSection('header-title')
                        @yield('header-title')
                    @else
                        <h1 class="text-xl font-semibold text-[--color-ink]">@yield('title', 'CEMS-MY')</h1>
                    @endif
                </div>

                {{-- Header Actions --}}
                <div class="flex items-center gap-4">
                    @yield('header-actions')
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="page-container">
                {{-- Flash Messages --}}
                @if(session('success'))
                    <div class="alert alert-success mb-6 animate-slideDown">
                        <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="alert-content">
                            <p class="alert-title">Success</p>
                            <p class="alert-description">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger mb-6 animate-slideDown">
                        <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="alert-content">
                            <p class="alert-title">Error</p>
                            <p class="alert-description">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning mb-6 animate-slideDown">
                        <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <div class="alert-content">
                            <p class="alert-title">Warning</p>
                            <p class="alert-description">{{ session('warning') }}</p>
                        </div>
                    </div>
                @endif

                {{-- Page Content --}}
                {{ $slot }}
            </div>
        </main>

        {{-- Footer --}}
        <footer class="border-t border-[--color-border] py-6 px-8 bg-[--color-canvas]">
            <div class="flex items-center justify-between text-sm text-[--color-ink-muted]">
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 bg-[--color-accent] rounded flex items-center justify-center">
                        <span class="text-white font-bold text-xs">C</span>
                    </div>
                    <span>CEMS-MY v1.0 - Bank Negara Malaysia Compliant MSB Management System</span>
                </div>
                <div class="flex items-center gap-6">
                    <a href="#" class="hover:text-[--color-ink] transition-colors">Documentation</a>
                    <a href="#" class="hover:text-[--color-ink] transition-colors">Support</a>
                    <a href="#" class="hover:text-[--color-ink] transition-colors">Security</a>
                </div>
            </div>
        </footer>
    </div>
</div>