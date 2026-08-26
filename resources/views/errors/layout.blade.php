<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas-subtle text-ink flex items-center justify-center">
    <div class="w-full max-w-md p-6">
        <x-card>
            <div class="p-8 space-y-6">
                <x-page-header title="{{ config('app.name') }}" class="justify-center" />

                <div class="text-center space-y-3">
                    <p class="text-5xl font-bold text-primary">@yield('code')</p>
                    <h1 class="text-xl font-semibold text-ink">@yield('title')</h1>
                    <p class="text-sm text-ink-muted leading-relaxed">@yield('message')</p>
                </div>

                <div class="flex justify-center pt-2">
                    @yield('actions')
                </div>
            </div>
        </x-card>
    </div>
</body>
</html>
