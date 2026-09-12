@props([
    'unreadNotifications' => [],
    'unreadCount' => 0,
    'dlqCount' => 0,
])

{{-- Header notification bell (Section 3.10). Shows the unread in-app
     notification count on the bell, an amber DLQ chip for admins when
     transactions are stuck, and a dropdown with the latest unread items.
     The badge counts poll the unread-count endpoint every 60s so they stay
     fresh across tabs without a websocket dependency. --}}
<div class="relative flex items-center gap-2"
     x-data="{
         open: false,
         count: {{ $unreadCount }},
         dlq: {{ $dlqCount }},
         poll() {
             fetch('{{ route('notifications.unread-count') }}', {
                 headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
             })
             .then(r => r.json())
             .then(d => { this.count = d.count; this.dlq = d.dlq_count; })
             .catch(() => {});
         },
         init() {
             // Server data is already fresh on page load (NotificationComposer);
             // only the periodic refresh is needed to pick up changes made in
             // other tabs. Polling is exempt from the session idle timer.
             setInterval(() => this.poll(), 60000);
         }
     }"
     @click.outside="open = false">

    {{-- DLQ chip - admin only (data is nulled for non-admins upstream) --}}
    <template x-if="dlq > 0">
        <a href="{{ route('transactions.dlq') }}"
           class="header-dlq-chip inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-warning/15 text-warning text-xs font-semibold hover:bg-warning/25 transition-colors"
           title="Dead letter queue - transactions awaiting manual review">
            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
            DLQ
            <span class="tabular-nums" x-text="dlq"></span>
        </a>
    </template>

    {{-- Bell --}}
    <button type="button"
            aria-label="Notifications"
            @click="open = !open"
            class="relative p-2 rounded-lg hover:bg-border/70 transition-colors focus:outline-none focus:ring-2 focus:ring-sidebar-ring">
        <x-heroicon-o-bell class="w-5 h-5" />
        <template x-if="count > 0">
            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-danger text-on-primary text-[10px] font-semibold flex items-center justify-center"
                  x-text="count > 99 ? '99+' : count"></span>
        </template>
    </button>

    {{-- Dropdown --}}
    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute right-0 top-full mt-2 w-80 max-w-[calc(100vw-2rem)] bg-surface border border-border rounded-lg shadow-lg z-50 overflow-hidden">

        <div class="flex items-center justify-between px-4 py-3 border-b border-border">
            <span class="text-sm font-semibold">Notifications</span>
            @if(! empty($unreadNotifications))
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-xs text-primary hover:underline">Mark all as read</button>
                </form>
            @endif
        </div>

        <ul class="max-h-80 overflow-y-auto divide-y divide-border">
            @forelse($unreadNotifications as $notification)
                <li class="px-4 py-3 flex items-start gap-3 hover:bg-border/40 transition-colors">
                    <span class="mt-1.5 w-2 h-2 rounded-full bg-primary shrink-0"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-ink truncate">{{ $notification['title'] }}</p>
                        @if(! empty($notification['message']))
                            <p class="text-xs text-ink-muted truncate mt-0.5" title="{{ $notification['message'] }}">{{ $notification['message'] }}</p>
                        @endif
                        <p class="text-xs text-ink-muted/60 mt-0.5">{{ $notification['time'] }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if(! empty($notification['url']))
                            <a href="{{ $notification['url'] }}" class="text-xs text-primary hover:underline">View</a>
                        @endif
                        <form method="POST" action="{{ route('notifications.read', $notification['id']) }}">
                            @csrf
                            <button type="submit" class="text-xs text-ink-muted hover:text-ink transition-colors" title="Mark as read">✓</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="px-4 py-8 text-sm text-ink-muted text-center">No unread notifications.</li>
            @endforelse
        </ul>

        <div class="px-4 py-2 border-t border-border text-center">
            <span class="text-xs text-ink-muted/60">Badge counts refresh automatically</span>
        </div>
    </div>
</div>
