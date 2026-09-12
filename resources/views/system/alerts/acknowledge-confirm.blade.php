<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Acknowledge Alert - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas-subtle text-ink flex items-center justify-center">
    <div class="w-full max-w-md p-6">
        <x-card>
            <div class="p-8 space-y-6">
                <x-page-header title="Acknowledge System Alert" class="justify-center" />

                @if(session('success'))
                    <x-alert type="success">{{ session('success') }}</x-alert>
                @elseif(session('info'))
                    <x-alert type="info">{{ session('info') }}</x-alert>
                @elseif(session('error'))
                    <x-alert type="error">{{ session('error') }}</x-alert>
                @endif

                <p>{{ $alert->message }}</p>

                <p class="text-sm text-ink-muted">
                    Level: {{ ucfirst($alert->level->value) }}
                    · Source: {{ $alert->source ?? 'N/A' }}
                    · Raised {{ $alert->created_at->diffForHumans() }}
                </p>

                @if($alert->isAcknowledged())
                    <p class="text-sm text-ink-muted">This alert has already been acknowledged.</p>
                @else
                    <form method="POST" action="{{ route('system.alerts.acknowledge', $alert) }}" class="space-y-4">
                        @csrf
                        <x-button type="submit" variant="primary" class="w-full">Acknowledge Alert</x-button>
                    </form>
                @endif

                <a href="{{ route('system.alerts.index') }}"
                   class="block text-sm text-info-text hover:text-info text-center">
                    Back to System Alerts
                </a>
            </div>
        </x-card>
    </div>
</body>
</html>
