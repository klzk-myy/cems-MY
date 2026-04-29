<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Month-End Close - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        <aside class="w-60 bg-white border-r border-[#e5e5e5] flex flex-col shrink-0">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h1 class="text-lg font-semibold text-[#171717]">CEMS-MY</h1>
            </div>
            <nav class="flex-1 p-4 space-y-6 overflow-y-auto">
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Accounting</div>
                    <a href="{{ route('accounting.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]">Dashboard</a>
                    <a href="{{ route('accounting.month-end') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 bg-[#f7f7f8] text-[#171717] font-medium">Month-End Close</a>
                </div>
            </nav>
        </aside>
        <main class="flex-1 bg-[#fafafa]">
            <div class="px-8 py-6">
                <h2 class="text-2xl font-semibold text-[#171717] mb-6">Month-End Close</h2>
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
                    <h3 class="text-lg font-medium mb-4">Status for {{ $selectedDate }}</h3>
                    @if(isset($status))
                        <div class="space-y-3">
                            @foreach($status as $key => $value)
                                <div class="flex justify-between py-2 border-b border-[#e5e5e5] last:border-0">
                                    <span class="text-sm text-[#6b6b6b]">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                                    <span class="text-sm font-medium">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-[#6b6b6b]">No status information available.</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('accounting.month-end.close') }}" class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    @csrf
                    <input type="hidden" name="date" value="{{ $selectedDate }}">
                    <p class="text-sm text-[#6b6b6b] mb-4">Run month-end close for {{ $selectedDate }}. This will finalize all accounting periods and prevent further modifications.</p>
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]" onclick="return confirm('Are you sure you want to run month-end close?')">
                        Run Month-End Close
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>