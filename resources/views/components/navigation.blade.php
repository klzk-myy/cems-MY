@props(['collapsed' => false])

@php
    $navigation = \App\Config\Navigation::getForRole(auth()->user()->role);
    $currentRoute = request()->route()->getName();
@endphp

<nav x-data="{ collapsed: {{ $collapsed ? 'true' : 'false' }} }" 
     :class="collapsed ? 'w-20' : 'w-64'"
     class="bg-gray-900 text-white min-h-screen flex flex-col transition-all duration-300">
    
    {{-- Brand --}}
    <div class="p-4 border-b border-gray-700 flex items-center justify-between">
        <h1 x-show="!collapsed" class="text-xl font-bold transition-opacity duration-300">{{ config('app.name') }}</h1>
        <button @click="collapsed = !collapsed" 
                class="p-2 rounded hover:bg-gray-800 transition-colors">
            <svg x-show="!collapsed" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7 7" />
            </svg>
            <svg x-show="collapsed" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            </svg>
        </button>
    </div>

    {{-- Navigation Items --}}
    <ul class="flex-1 overflow-y-auto py-4 space-y-1">
        @foreach($navigation as $section => $config)
            @if($section === 'main')
                {{-- Main section items (no header) --}}
                @foreach($config['items'] as $item)
                    <li>
                        <a href="{{ route($item['route']) }}" 
                           class="flex items-center {{ $collapsed ? 'justify-center px-3' : 'px-4' }} py-2 hover:bg-gray-800 {{ request()->routeIs($item['route']) ? 'bg-gray-800' : '' }}"
                           title="{{ $collapsed ? $item['label'] : '' }}">
                            <x-dynamic-component :component="'heroicon-o-' . ($item['icon'] ?? 'circle')" class="w-5 h-5 flex-shrink-0" />
                            <span x-show="!collapsed" class="ml-3">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            @else
                {{-- Section header --}}
                <li x-show="!collapsed" class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">{{ $config['label'] }}</li>
                
                {{-- Section items --}}
                @foreach($config['items'] as $item)
                    <li>
                        <a href="{{ route($item['route']) }}" 
                           class="flex items-center {{ $collapsed ? 'justify-center px-3' : 'pl-6' }} py-2 text-sm hover:bg-gray-800 {{ request()->routeIs($item['route'] . '*') ? 'bg-gray-800' : '' }}"
                           title="{{ $collapsed ? $item['label'] : '' }}">
                            <x-dynamic-component :component="'heroicon-o-' . ($item['icon'] ?? 'circle')" class="w-5 h-5 flex-shrink-0" />
                            <span x-show="!collapsed" class="ml-3">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            @endif
        @endforeach
    </ul>

    {{-- User Menu & Logout --}}
    <div class="p-4 border-t border-gray-700">
        @if(!$collapsed)
            <div class="mb-3 flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center">
                    <span class="text-sm font-medium">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ auth()->user()->role->value }}</p>
                </div>
            </div>
        @endif
        
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" 
                    class="w-full {{ $collapsed ? 'px-3' : 'px-4' }} py-2 hover:bg-gray-800 rounded flex items-center justify-center"
                    title="{{ $collapsed ? 'Logout' : '' }}">
                <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5" />
                <span x-show="!collapsed" class="ml-2">Logout</span>
            </button>
        </form>
    </div>
</nav>