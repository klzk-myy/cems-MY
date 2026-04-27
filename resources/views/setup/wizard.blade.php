<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            <div class="max-w-2xl mx-auto">
                <h1 class="text-2xl font-semibold text-[#171717] mb-2">Setup Wizard</h1>
                <p class="text-sm text-[#6b6b6b] mb-6">Step {{ $step }} of 4</p>
                <div class="w-full bg-[#e5e5e5] rounded-full h-2 mb-8">
                    <div class="bg-[#0a0a0a] h-2 rounded-full" style="width: {{ ($step / 4) * 100 }}%;"></div>
                </div>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    @if($step === 1)
                    <h2 class="text-lg font-semibold text-[#171717] mb-4">Business Configuration</h2>
                    <p class="text-[#6b6b6b]">Configure your business settings</p>
                    @elseif($step === 2)
                    <h2 class="text-lg font-semibold text-[#171717] mb-4">Currency Setup</h2>
                    <p class="text-[#6b6b6b]">Set up supported currencies</p>
                    @elseif($step === 3)
                    <h2 class="text-lg font-semibold text-[#171717] mb-4">Branch Setup</h2>
                    <p class="text-[#6b6b6b]">Configure your branch locations</p>
                    @else
                    <h2 class="text-lg font-semibold text-[#171717] mb-4">Initial Admin User</h2>
                    <p class="text-[#6b6b6b]">Create the first admin account</p>
                    @endif
                </div>
            </div>
        </main>
    </div>
</body>
</html>