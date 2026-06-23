<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas-subtle text-ink flex items-center justify-center">
    <div class="w-full max-w-md p-6">
        <x-card>
            <div class="p-8 space-y-6">
                <x-page-header title="{{ config('app.name') }}" class="justify-center" />

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    @if($errors->any())
                        <x-alert type="error">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    @endif

                    <x-input type="text" name="username" label="Username" required value="{{ old('username') }}" inline />
                    <x-input type="password" name="password" label="Password" required inline />

                    <x-button type="submit" variant="primary" class="w-full">Sign In</x-button>
                </form>
            </div>
        </x-card>
    </div>
</body>
</html>
