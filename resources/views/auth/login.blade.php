<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center">
    <div class="w-full max-w-md p-6">
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-8">
            <h1 class="text-2xl font-bold text-center mb-6">{{ config('app.name') }}</h1>

            <form method="POST" action="{{ route('login') }}">
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

                <x-input type="text" name="username" label="Username" required value="{{ old('username') }}" />
                <x-input type="password" name="password" label="Password" required />

                <x-button type="submit" variant="primary" class="w-full">Sign In</x-button>
            </form>
        </div>
    </div>
</body>
</html>