<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto flex items-center justify-center">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-8 max-w-md w-full">
                <h1 class="text-2xl font-semibold text-[#171717] mb-2">System Setup</h1>
                <p class="text-sm text-[#6b6b6b] mb-6">Configure your CEMS-MY installation</p>
                <div class="mb-6">
                    <div class="text-sm text-[#6b6b6b] mb-2">Setup Status</div>
                    <div class="w-full bg-[#f7f7f8] rounded-full h-2">
                        <div class="bg-[#0a0a0a] h-2 rounded-full" style="width: {{ $isSetupComplete ? '100' : '25' }}%;"></div>
                    </div>
                    <p class="text-xs text-[#6b6b6b] mt-2">{{ $isSetupComplete ? 'Setup complete' : 'Step ' . ($currentStep ?? 1) . ' of 4' }}</p>
                </div>
                @if($isSetupComplete)
                <a href="{{ route('dashboard') }}" class="block w-full px-4 py-2.5 text-sm font-medium text-center text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Go to Dashboard</a>
                @else
                <a href="{{ route('setup.wizard') }}" class="block w-full px-4 py-2.5 text-sm font-medium text-center text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Continue Setup</a>
                @endif
            </div>
        </main>
    </div>
</body>
</html>